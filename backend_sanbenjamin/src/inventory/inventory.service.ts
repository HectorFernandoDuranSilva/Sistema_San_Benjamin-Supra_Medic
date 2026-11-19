import { BadRequestException, Injectable, NotFoundException } from '@nestjs/common';
import { PrismaService } from '../prisma/prisma.service';
import { LotDto, MovementDto } from './dto/lot.dto';
@Injectable()
export class InventoryService {
  constructor(private readonly prisma: PrismaService) {}
  products() { return this.prisma.product.findMany({ where: { active: true }, select: { id: true, code: true, name: true, stock: true, minStock: true, category: { select: { name: true } } }, orderBy: { name: 'asc' } }); }
  lots() { return this.prisma.inventoryLot.findMany({ where: { active: true }, include: { product: true }, orderBy: { expiryDate: 'asc' } }); }
  movements() { return this.prisma.inventoryMovement.findMany({ include: { product: true, lot: true }, orderBy: { createdAt: 'desc' }, take: 200 }); }
  async createLot(dto: LotDto) { const product = await this.prisma.product.findUnique({ where: { id: dto.productId } }); if (!product) throw new NotFoundException('Producto no encontrado'); return this.prisma.inventoryLot.create({ data: { ...dto, expiryDate: dto.expiryDate ? new Date(dto.expiryDate) : undefined } }); }
  async adjust(dto: MovementDto) { if (dto.quantity === 0) throw new BadRequestException('La cantidad no puede ser cero'); return this.prisma.$transaction(async (tx) => { const product = await tx.product.findUnique({ where: { id: dto.productId } }); if (!product) throw new NotFoundException('Producto no encontrado'); const delta = dto.type === 'OUT' ? -Math.abs(dto.quantity) : Math.abs(dto.quantity); if (Number(product.stock) + delta < 0) throw new BadRequestException('El ajuste deja stock negativo'); const result = await tx.product.update({ where: { id: dto.productId }, data: { stock: { increment: delta } } }); if (dto.lotId) await tx.inventoryLot.update({ where: { id: dto.lotId }, data: { quantity: { increment: delta } } }); await tx.inventoryMovement.create({ data: { productId: dto.productId, lotId: dto.lotId, quantity: Math.abs(dto.quantity), type: dto.type, reference: dto.reference } }); return result; }); }
}
