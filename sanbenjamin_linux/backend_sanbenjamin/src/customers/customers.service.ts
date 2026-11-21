import { Injectable, NotFoundException } from '@nestjs/common';
import { PrismaService } from '../prisma/prisma.service';
import { CustomerDto } from './dto/customer.dto';
@Injectable()
export class CustomersService {
  constructor(private readonly prisma: PrismaService) {}
  findAll() { return this.prisma.customer.findMany({ where: { active: true }, orderBy: { name: 'asc' } }); }
  async findOne(id: number) { const row = await this.prisma.customer.findUnique({ where: { id } }); if (!row) throw new NotFoundException('Cliente no encontrado'); return row; }
  create(dto: CustomerDto) { return this.prisma.customer.create({ data: dto }); }
  async update(id: number, dto: CustomerDto) { await this.findOne(id); return this.prisma.customer.update({ where: { id }, data: dto }); }
  async remove(id: number) { await this.findOne(id); return this.prisma.customer.update({ where: { id }, data: { active: false } }); }
}
