import { BadRequestException, Injectable } from '@nestjs/common';
import { PrismaService } from '../prisma/prisma.service';
import { SaleDto } from './dto/sale.dto';
import { AuditService } from '../audit/audit.service';
@Injectable()
export class SalesService {
  constructor(private readonly prisma: PrismaService, private readonly audit: AuditService) {}
  findAll() { return this.prisma.sale.findMany({ include: { customer: true, items: { include: { product: true } } }, orderBy: { createdAt: 'desc' } }); }
  async cancel(id: number, userId: number) { return this.prisma.$transaction(async (tx) => { const sale = await tx.sale.findUnique({ where: { id }, include: { items: true } }); if (!sale) throw new BadRequestException('Venta no encontrada'); if (sale.status === 'CANCELLED') throw new BadRequestException('La venta ya está anulada'); for (const item of sale.items) await tx.product.update({ where: { id: item.productId }, data: { stock: { increment: item.quantity } } }); const result = await tx.sale.update({ where: { id }, data: { status: 'CANCELLED' } }); await this.audit.record('CANCEL', 'sale', id, `Venta ${sale.number}`, userId); return result; }); }
  async update(id: number, dto: SaleDto, userId: number) { return this.prisma.$transaction(async (tx) => { const old = await tx.sale.findUnique({ where: { id }, include: { items: true } }); if (!old || old.status === 'CANCELLED') throw new BadRequestException('Venta no editable'); for (const item of old.items) await tx.product.update({ where: { id: item.productId }, data: { stock: { increment: item.quantity } } }); let total = 0; const items = []; for (const item of dto.items) { const product = await tx.product.findUnique({ where: { id: item.productId } }); if (!product || Number(product.stock) < item.quantity) throw new BadRequestException('Stock insuficiente'); const subtotal = item.quantity * item.unitPrice; total += subtotal; items.push({ ...item, subtotal }); } await tx.saleItem.deleteMany({ where: { saleId: id } }); const result = await tx.sale.update({ where: { id }, data: { total, customerId: dto.customerId, items: { create: items } }, include: { items: true } }); await Promise.all(items.map((item) => tx.product.update({ where: { id: item.productId }, data: { stock: { decrement: item.quantity } } }))); await this.audit.record('UPDATE', 'sale', id, `Venta ${old.number}`, userId); return result; }); }
  async create(dto: SaleDto, userId: number) {
    if (!dto.items.length) throw new BadRequestException('La venta debe tener productos');
    const sale = await this.prisma.$transaction(async (tx) => {
      let total = 0;
      const items = [] as { productId: number; quantity: number; unitPrice: number; subtotal: number }[];
      for (const item of dto.items) {
        const product = await tx.product.findUnique({ where: { id: item.productId } });
        if (!product || !product.active) throw new BadRequestException(`Producto ${item.productId} no encontrado`);
        if (Number(product.stock) < item.quantity) throw new BadRequestException(`Stock insuficiente para ${product.name}`);
        const subtotal = item.quantity * item.unitPrice; total += subtotal;
        items.push({ ...item, subtotal });
      }
      const number = `V-${Date.now()}`;
      const sale = await tx.sale.create({ data: { number, total, userId, customerId: dto.customerId, items: { create: items } }, include: { items: true } });
      for (const item of items) await tx.product.update({ where: { id: item.productId }, data: { stock: { decrement: item.quantity } } });
      return sale;
    });
    await this.audit.record('CREATE', 'sale', sale.id, `Venta ${sale.number}`, userId);
    return sale;
  }
}
