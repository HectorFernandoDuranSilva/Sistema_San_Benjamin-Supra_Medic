import { Injectable, NotFoundException } from '@nestjs/common';
import PDFDocument from 'pdfkit';
import { PrismaService } from '../prisma/prisma.service';
@Injectable()
export class DocumentsService {
  constructor(private readonly prisma: PrismaService) {}
  async saleReceipt(id: number) { const sale = await this.prisma.sale.findUnique({ where: { id }, include: { customer: true, items: { include: { product: true } } } }); if (!sale) throw new NotFoundException('Venta no encontrada'); return this.receipt('RECIBO DE VENTA', sale.number, sale.customer?.name ?? 'General', sale.total, sale.items.map((i) => `${i.product.name} | ${i.quantity} x ${Number(i.unitPrice).toFixed(2)} = ${Number(i.subtotal).toFixed(2)}`)); }
  async purchaseReceipt(id: number) { const purchase = await this.prisma.purchase.findUnique({ where: { id }, include: { supplier: true, items: { include: { product: true } } } }); if (!purchase) throw new NotFoundException('Compra no encontrada'); return this.receipt('RECIBO DE COMPRA', purchase.number, purchase.supplier?.name ?? 'General', purchase.total, purchase.items.map((i) => `${i.product.name} | ${i.quantity} x ${Number(i.unitCost).toFixed(2)} = ${Number(i.subtotal).toFixed(2)}`)); }
  private receipt(title: string, number: string, person: string, total: unknown, lines: string[]) { return new Promise<Buffer>((resolve) => { const doc = new PDFDocument({ margin: 42 }); const chunks: Buffer[] = []; doc.on('data', (c) => chunks.push(c)); doc.on('end', () => resolve(Buffer.concat(chunks))); doc.fontSize(18).text('SAN BENJAMIN - SUPRAMEDIC', { align: 'center' }).moveDown(); doc.fontSize(14).text(title, { align: 'center' }).moveDown(); doc.fontSize(10).text(`Documento: ${number}`).text(`Cliente/Proveedor: ${person}`).text(`Fecha: ${new Date().toLocaleString('es-BO')}`).moveDown(); lines.forEach((line) => doc.text(line)); doc.moveDown().fontSize(13).text(`TOTAL: ${Number(total).toFixed(2)}`, { align: 'right' }); doc.end(); }); }
}
