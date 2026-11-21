import { Injectable } from '@nestjs/common';
import { PrismaService } from '../prisma/prisma.service';
import { ExpenseDto } from './dto/expense.dto';
@Injectable()
export class ExpensesService { constructor(private readonly prisma: PrismaService) {} list() { return this.prisma.operationalExpense.findMany({ orderBy: { expenseDate: 'desc' } }); } create(dto: ExpenseDto) { return this.prisma.operationalExpense.create({ data: { ...dto, expenseDate: dto.expenseDate ? new Date(dto.expenseDate) : undefined } }); } }
