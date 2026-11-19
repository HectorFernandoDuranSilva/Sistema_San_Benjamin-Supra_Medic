import { Body, Controller, Get, Post, UseGuards } from '@nestjs/common';
import { ApiBearerAuth, ApiTags } from '@nestjs/swagger';
import { JwtAuthGuard } from '../auth/jwt-auth.guard';
import { ExpenseDto } from './dto/expense.dto';
import { ExpensesService } from './expenses.service';
@ApiTags('expenses') @ApiBearerAuth() @UseGuards(JwtAuthGuard) @Controller('expenses')
export class ExpensesController { constructor(private readonly service: ExpensesService) {} @Get() list() { return this.service.list(); } @Post() create(@Body() dto: ExpenseDto) { return this.service.create(dto); } }
