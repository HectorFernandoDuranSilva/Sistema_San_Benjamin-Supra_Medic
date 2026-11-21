import { ConflictException, Injectable, NotFoundException } from '@nestjs/common';
import { PrismaService } from '../prisma/prisma.service';
import { ProductDto } from './dto/product.dto';

@Injectable()
export class ProductsService {
  constructor(private readonly prisma: PrismaService) {}

  findAll() {
    return this.prisma.product.findMany({ where: { active: true }, include: { category: true }, orderBy: { name: 'asc' } });
  }

  async findOne(id: number) {
    const product = await this.prisma.product.findUnique({ where: { id }, include: { category: true } });
    if (!product) throw new NotFoundException('Producto no encontrado');
    return product;
  }

  async create(dto: ProductDto) {
    try { return await this.prisma.product.create({ data: { ...dto, stock: 0 } }); }
    catch (error) {
      if ((error as { code?: string }).code === 'P2002') throw new ConflictException('El código ya existe');
      throw error;
    }
  }

  async update(id: number, dto: ProductDto) {
    await this.findOne(id);
    return this.prisma.product.update({ where: { id }, data: dto });
  }

  async remove(id: number) {
    await this.findOne(id);
    return this.prisma.product.update({ where: { id }, data: { active: false } });
  }
}
