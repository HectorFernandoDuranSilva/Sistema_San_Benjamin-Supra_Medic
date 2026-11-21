import { BadRequestException, Injectable } from '@nestjs/common';
import { PrismaService } from '../prisma/prisma.service';
import { PurchaseDto } from './dto/purchase.dto';
import { AuditService } from '../audit/audit.service';
@Injectable()
export class PurchasesService {
  constructor(private readonly prisma: PrismaService, private readonly audit: AuditService) {}
  findAll() { return this.prisma.purchase.findMany({ include: { supplier: true, items: { include: { product: true } } }, orderBy: { createdAt: 'desc' } }); }
  async cancel(id: number, userId: number) { return this.prisma.$transaction(async (tx) => { const purchase = await tx.purchase.findUnique({ where: { id }, include: { items: true } }); if (!purchase) throw new BadRequestException('Compra no encontrada'); if (purchase.status === 'CANCELLED') throw new BadRequestException('La compra ya está anulada'); for (const item of purchase.items) { const product = await tx.product.findUnique({ where: { id: item.productId } }); if (!product || Number(product.stock) < Number(item.quantity)) throw new BadRequestException('No se puede anular: stock ya utilizado'); await tx.product.update({ where: { id: item.productId }, data: { stock: { decrement: item.quantity } } }); } const result = await tx.purchase.update({ where: { id }, data: { status: 'CANCELLED' } }); await this.audit.record('CANCEL', 'purchase', id, `Compra ${purchase.number}`, userId); return result; }); }
  async update(id: number, dto: PurchaseDto, userId: number) { return this.prisma.$transaction(async (tx) => { const old = await tx.purchase.findUnique({ where: { id }, include: { items: true } }); if (!old || old.status === 'CANCELLED') throw new BadRequestException('Compra no editable'); for (const item of old.items) await tx.product.update({ where: { id: item.productId }, data: { stock: { decrement: item.quantity } } }); let total = 0; const items = []; for (const item of dto.items) { const product = await tx.product.findUnique({ where: { id: item.productId } }); if (!product) throw new BadRequestException('Producto no encontrado'); const subtotal = item.quantity * item.unitCost; total += subtotal; items.push({ ...item, subtotal }); } await tx.purchaseItem.deleteMany({ where: { purchaseId: id } }); const result = await tx.purchase.update({ where: { id }, data: { total, supplierId: dto.supplierId, items: { create: items } }, include: { items: true } }); await Promise.all(items.map((item) => tx.product.update({ where: { id: item.productId }, data: { stock: { increment: item.quantity }, purchaseCost: item.unitCost } }))); await this.audit.record('UPDATE', 'purchase', id, `Compra ${old.number}`, userId); return result; }); }
  async create(dto: PurchaseDto, userId: number) {
    if (!dto.items.length) throw new BadRequestException('La compra debe tener productos');
    const purchase = await this.prisma.$transaction(async (tx) => {
      let total = 0; const items = dto.items.map((item) => { const subtotal = item.quantity * item.unitCost; total += subtotal; return { ...item, subtotal }; });
      for (const item of items) { const product = await tx.product.findUnique({ where: { id: item.productId } }); if (!product || !product.active) throw new BadRequestException(`Producto ${item.productId} no encontrado`); }
      const purchase = await tx.purchase.create({ data: { number: `C-${Date.now()}`, total, userId, supplierId: dto.supplierId, items: { create: items } }, include: { items: true } });
      for (const item of items) await tx.product.update({ where: { id: item.productId }, data: { stock: { increment: item.quantity }, purchaseCost: item.unitCost } });
      return purchase;
    });
    await this.audit.record('CREATE', 'purchase', purchase.id, `Compra ${purchase.number}`, userId);
    return purchase;
  }
}
