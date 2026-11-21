import { BadRequestException, Injectable, NotFoundException } from '@nestjs/common';
import { PrismaService } from '../prisma/prisma.service';
import { PaymentDto } from './dto/payment.dto';
import { AuditService } from '../audit/audit.service';

@Injectable()
export class PaymentsService {
  constructor(private readonly prisma: PrismaService, private readonly audit: AuditService) {}

  async forSale(saleId: number, dto: PaymentDto) {
    const sale = await this.prisma.sale.findUnique({ where: { id: saleId } });
    if (!sale) throw new NotFoundException('Venta no encontrada');
    const paid = await this.prisma.payment.aggregate({ _sum: { amount: true }, where: { saleId } });
    if (Number(paid._sum.amount ?? 0) + dto.amount > Number(sale.total)) throw new BadRequestException('El pago supera el total de la venta');
    const payment = await this.prisma.payment.create({ data: { ...dto, saleId } });
    await this.audit.record('CREATE', 'payment', payment.id, `Pago de venta ${sale.number}`);
    return payment;
  }

  async forPurchase(purchaseId: number, dto: PaymentDto) {
    const purchase = await this.prisma.purchase.findUnique({ where: { id: purchaseId } });
    if (!purchase) throw new NotFoundException('Compra no encontrada');
    const paid = await this.prisma.payment.aggregate({ _sum: { amount: true }, where: { purchaseId } });
    if (Number(paid._sum.amount ?? 0) + dto.amount > Number(purchase.total)) throw new BadRequestException('El pago supera el total de la compra');
    const payment = await this.prisma.payment.create({ data: { ...dto, purchaseId } });
    await this.audit.record('CREATE', 'payment', payment.id, `Pago de compra ${purchase.number}`);
    return payment;
  }

  list() { return this.prisma.payment.findMany({ include: { sale: true, purchase: true }, orderBy: { createdAt: 'desc' } }); }
  async changeStatus(id: number, status: 'ACTIVE'|'CANCELLED') { const payment = await this.prisma.payment.findUnique({ where: { id } }); if (!payment) throw new NotFoundException('Pago no encontrado'); return this.prisma.payment.update({ where: { id }, data: { status } }); }
}
