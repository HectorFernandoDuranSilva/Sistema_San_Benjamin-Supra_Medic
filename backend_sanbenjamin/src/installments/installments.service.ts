import { BadRequestException, Injectable, NotFoundException } from '@nestjs/common';
import { PrismaService } from '../prisma/prisma.service';
import { InstallmentDto } from './dto/installment.dto';

@Injectable()
export class InstallmentsService {
  constructor(private readonly prisma: PrismaService) {}
  list() { return this.prisma.installment.findMany({ include: { sale: true, purchase: true }, orderBy: { dueDate: 'asc' } }); }
  async forSale(saleId: number, dto: InstallmentDto) {
    const sale = await this.prisma.sale.findUnique({ where: { id: saleId } });
    if (!sale) throw new NotFoundException('Venta no encontrada');
    await this.checkTotal(dto.amount, sale.total, await this.prisma.installment.aggregate({ _sum: { amount: true }, where: { saleId } }));
    return this.prisma.installment.create({ data: { amount: dto.amount, dueDate: new Date(dto.dueDate), saleId } });
  }
  async forPurchase(purchaseId: number, dto: InstallmentDto) {
    const purchase = await this.prisma.purchase.findUnique({ where: { id: purchaseId } });
    if (!purchase) throw new NotFoundException('Compra no encontrada');
    await this.checkTotal(dto.amount, purchase.total, await this.prisma.installment.aggregate({ _sum: { amount: true }, where: { purchaseId } }));
    return this.prisma.installment.create({ data: { amount: dto.amount, dueDate: new Date(dto.dueDate), purchaseId } });
  }
  private checkTotal(amount: number, total: unknown, aggregate: { _sum: { amount: unknown } }) {
    if (Number(aggregate._sum.amount ?? 0) + amount > Number(total)) throw new BadRequestException('Las cuotas superan el total del documento');
  }
  async changeStatus(id: number, status: 'PENDING'|'PAID'|'CANCELLED') { const installment = await this.prisma.installment.findUnique({ where: { id } }); if (!installment) throw new NotFoundException('Cuota no encontrada'); return this.prisma.installment.update({ where: { id }, data: { status } }); }
}
