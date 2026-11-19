import { BadRequestException, Injectable } from '@nestjs/common';
import { PrismaService } from '../prisma/prisma.service';
import { QuotationDto } from './dto/quotation.dto';
import { AuditService } from '../audit/audit.service';

@Injectable()
export class QuotationsService {
  constructor(private readonly prisma: PrismaService, private readonly audit: AuditService) {}
  findAll() { return this.prisma.quotation.findMany({ include: { customer: true, items: { include: { product: true } } }, orderBy: { createdAt: 'desc' } }); }
  async create(dto: QuotationDto) {
    if (!dto.items.length) throw new BadRequestException('La cotización debe tener productos');
    let total = 0;
    const items = dto.items.map((item) => { const subtotal = item.quantity * item.unitPrice; total += subtotal; return { ...item, subtotal }; });
    const quotation = await this.prisma.quotation.create({ data: { number: `COT-${Date.now()}`, total, customerId: dto.customerId, items: { create: items } }, include: { items: true } });
    await this.audit.record('CREATE', 'quotation', quotation.id, `Cotización ${quotation.number}`);
    return quotation;
  }
  async convert(id: number, userId: number) { return this.prisma.$transaction(async (tx) => { const quotation = await tx.quotation.findUnique({ where: { id }, include: { items: true } }); if (!quotation) throw new BadRequestException('Cotización no encontrada'); if (quotation.status === 'CONVERTED') throw new BadRequestException('La cotización ya fue convertida'); for (const item of quotation.items) { const product = await tx.product.findUnique({ where: { id: item.productId } }); if (!product || Number(product.stock) < Number(item.quantity)) throw new BadRequestException(`Stock insuficiente para ${item.productId}`); } const sale = await tx.sale.create({ data: { number: `V-${Date.now()}`, total: quotation.total, customerId: quotation.customerId, userId, items: { create: quotation.items.map((item) => ({ productId: item.productId, quantity: item.quantity, unitPrice: item.unitPrice, subtotal: item.subtotal })) } } }); for (const item of quotation.items) await tx.product.update({ where: { id: item.productId }, data: { stock: { decrement: item.quantity } } }); await tx.quotation.update({ where: { id }, data: { status: 'CONVERTED' } }); return sale; }); }
}
