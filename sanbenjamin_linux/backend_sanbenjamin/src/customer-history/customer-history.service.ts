import { Injectable, NotFoundException } from '@nestjs/common';
import { PrismaService } from '../prisma/prisma.service';
import { AccountPaymentDto } from './dto/account-payment.dto';
@Injectable()
export class CustomerHistoryService {
  constructor(private readonly prisma: PrismaService) {}
  async history(customerId: number) { const customer = await this.prisma.customer.findUnique({ where: { id: customerId }, include: { sales: { include: { payments: true }, orderBy: { createdAt: 'desc' } }, accountPayments: { orderBy: { createdAt: 'desc' } } } }); if (!customer) throw new NotFoundException('Cliente no encontrado'); const charged = customer.sales.filter((sale) => sale.status === 'ACTIVE').reduce((sum, sale) => sum + Number(sale.total), 0); const paidSales = customer.sales.flatMap((sale) => sale.payments).reduce((sum, payment) => sum + Number(payment.amount), 0); const accountPaid = customer.accountPayments.reduce((sum, payment) => sum + Number(payment.amount), 0); return { customer: { id: customer.id, name: customer.name }, sales: customer.sales, payments: customer.accountPayments, charged, paid: paidSales + accountPaid, balance: charged - paidSales - accountPaid }; }
  listPayments() { return this.prisma.customerAccountPayment.findMany({ include: { customer: true }, orderBy: { createdAt: 'desc' } }); }
  async createPayment(dto: AccountPaymentDto) { const customer = await this.prisma.customer.findUnique({ where: { id: dto.customerId } }); if (!customer) throw new NotFoundException('Cliente no encontrado'); return this.prisma.customerAccountPayment.create({ data: dto }); }
}
