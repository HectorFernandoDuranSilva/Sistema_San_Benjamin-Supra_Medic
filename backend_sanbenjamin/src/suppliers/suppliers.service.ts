import { Injectable, NotFoundException } from '@nestjs/common';
import { PrismaService } from '../prisma/prisma.service';
import { SupplierDto } from './dto/supplier.dto';
@Injectable()
export class SuppliersService {
  constructor(private readonly prisma: PrismaService) {}
  findAll() { return this.prisma.supplier.findMany({ where: { active: true }, orderBy: { name: 'asc' } }); }
  async findOne(id: number) { const row = await this.prisma.supplier.findUnique({ where: { id } }); if (!row) throw new NotFoundException('Proveedor no encontrado'); return row; }
  create(dto: SupplierDto) { return this.prisma.supplier.create({ data: dto }); }
  async update(id: number, dto: SupplierDto) { await this.findOne(id); return this.prisma.supplier.update({ where: { id }, data: dto }); }
  async remove(id: number) { await this.findOne(id); return this.prisma.supplier.update({ where: { id }, data: { active: false } }); }
}
