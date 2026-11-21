import { Injectable } from '@nestjs/common';
import { PrismaService } from '../prisma/prisma.service';
import ExcelJS from 'exceljs';
import PDFDocument from 'pdfkit';

@Injectable()
export class ReportsService {
  constructor(private readonly prisma: PrismaService) {}

  async summary() {
    const [products, categories, customers, suppliers, sales, purchases, lowStock, expenses] = await Promise.all([
      this.prisma.product.count({ where: { active: true } }),
      this.prisma.category.count({ where: { active: true } }),
      this.prisma.customer.count({ where: { active: true } }),
      this.prisma.supplier.count({ where: { active: true } }),
      this.prisma.sale.aggregate({ _sum: { total: true }, _count: { id: true }, where: { status: 'ACTIVE' } }),
      this.prisma.purchase.aggregate({ _sum: { total: true }, _count: { id: true }, where: { status: 'ACTIVE' } }),
      this.prisma.product.count({ where: { active: true, stock: { lte: 0 } } }),
      this.prisma.operationalExpense.aggregate({ _sum: { amount: true } }),
    ]);
    return { products, categories, customers, suppliers, sales: { count: sales._count.id, total: sales._sum.total ?? 0 }, purchases: { count: purchases._count.id, total: purchases._sum.total ?? 0 }, expenses: expenses._sum.amount ?? 0, lowStock };
  }

  async monthly(year: number, month: number) {
    const start = new Date(Date.UTC(year, month - 1, 1));
    const end = new Date(Date.UTC(year, month, 1));
    const [sales, purchases, expenses] = await Promise.all([
      this.prisma.sale.aggregate({ _sum: { total: true }, where: { status: 'ACTIVE', createdAt: { gte: start, lt: end } } }),
      this.prisma.purchase.aggregate({ _sum: { total: true }, where: { status: 'ACTIVE', createdAt: { gte: start, lt: end } } }),
      this.prisma.operationalExpense.aggregate({ _sum: { amount: true }, where: { expenseDate: { gte: start, lt: end } } }),
    ]);
    const salesTotal = Number(sales._sum.total ?? 0), purchasesTotal = Number(purchases._sum.total ?? 0), expensesTotal = Number(expenses._sum.amount ?? 0);
    return { year, month, sales: salesTotal, purchases: purchasesTotal, expenses: expensesTotal, result: salesTotal - purchasesTotal - expensesTotal };
  }

  async salesRows() {
    const sales = await this.prisma.sale.findMany({ include: { customer: true, user: true, items: { include: { product: true } } }, orderBy: { createdAt: 'desc' } });
    return sales.flatMap((sale) => sale.items.map((item) => ({ number: sale.number, date: sale.createdAt.toISOString().slice(0, 10), customer: sale.customer?.name ?? 'General', user: sale.user.name, product: item.product.name, quantity: Number(item.quantity), unitPrice: Number(item.unitPrice), subtotal: Number(item.subtotal) })));
  }

  async salesCsv() {
    const rows = await this.salesRows();
    const header = 'Numero,Fecha,Cliente,Usuario,Producto,Cantidad,Precio unitario,Subtotal';
    const escape = (value: unknown) => `"${String(value).replaceAll('"', '""')}"`;
    return Buffer.from([header, ...rows.map((row) => Object.values(row).map(escape).join(','))].join('\n'), 'utf8');
  }

  async salesExcel() {
    const rows = await this.salesRows();
    const workbook = new ExcelJS.Workbook();
    const sheet = workbook.addWorksheet('Ventas');
    sheet.columns = [{ header: 'Número', key: 'number' }, { header: 'Fecha', key: 'date' }, { header: 'Cliente', key: 'customer' }, { header: 'Usuario', key: 'user' }, { header: 'Producto', key: 'product' }, { header: 'Cantidad', key: 'quantity' }, { header: 'Precio unitario', key: 'unitPrice' }, { header: 'Subtotal', key: 'subtotal' }];
    sheet.addRows(rows); sheet.getRow(1).font = { bold: true }; sheet.columns.forEach((column) => { column.width = 18; });
    return Buffer.from(await workbook.xlsx.writeBuffer());
  }

  async salesPdf() {
    const rows = await this.salesRows();
    return new Promise<Buffer>((resolve) => {
      const doc = new PDFDocument({ margin: 40 }); const chunks: Buffer[] = [];
      doc.on('data', (chunk) => chunks.push(chunk)); doc.on('end', () => resolve(Buffer.concat(chunks)));
      doc.fontSize(18).text('Reporte de ventas - San Benjamin', { align: 'center' }).moveDown();
      doc.fontSize(9).text(`Generado: ${new Date().toLocaleString('es-BO')}`).moveDown();
      rows.forEach((row) => doc.text(`${row.number} | ${row.date} | ${row.product} | Cant.: ${row.quantity} | Subtotal: ${row.subtotal.toFixed(2)}`));
      if (!rows.length) doc.text('No existen ventas registradas.');
      doc.end();
    });
  }
}
