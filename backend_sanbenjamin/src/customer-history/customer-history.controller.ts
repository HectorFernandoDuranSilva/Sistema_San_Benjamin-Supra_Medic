import { Body, Controller, Get, Param, ParseIntPipe, Post, UseGuards } from '@nestjs/common';
import { ApiBearerAuth, ApiTags } from '@nestjs/swagger';
import { JwtAuthGuard } from '../auth/jwt-auth.guard';
import { AccountPaymentDto } from './dto/account-payment.dto';
import { CustomerHistoryService } from './customer-history.service';
@ApiTags('customer-history') @ApiBearerAuth() @UseGuards(JwtAuthGuard) @Controller('customer-history')
export class CustomerHistoryController { constructor(private readonly service: CustomerHistoryService) {} @Get('payments') listPayments() { return this.service.listPayments(); } @Get(':customerId') history(@Param('customerId', ParseIntPipe) customerId: number) { return this.service.history(customerId); } @Post('payments') createPayment(@Body() dto: AccountPaymentDto) { return this.service.createPayment(dto); } }
