import { Body, Controller, Get, Param, ParseIntPipe, Post, UseGuards } from '@nestjs/common';
import { ApiBearerAuth, ApiTags } from '@nestjs/swagger';
import { JwtAuthGuard } from '../auth/jwt-auth.guard';
import { PaymentDto } from './dto/payment.dto';
import { PaymentStatusDto } from './dto/payment-status.dto';
import { PaymentsService } from './payments.service';
@ApiTags('payments') @ApiBearerAuth() @UseGuards(JwtAuthGuard) @Controller('payments')
export class PaymentsController {
  constructor(private readonly service: PaymentsService) {}
  @Get() list() { return this.service.list(); }
  @Post('sales/:id') forSale(@Param('id', ParseIntPipe) id: number, @Body() dto: PaymentDto) { return this.service.forSale(id, dto); }
  @Post('purchases/:id') forPurchase(@Param('id', ParseIntPipe) id: number, @Body() dto: PaymentDto) { return this.service.forPurchase(id, dto); }
  @Post(':id/status') changeStatus(@Param('id', ParseIntPipe) id: number, @Body() dto: PaymentStatusDto) { return this.service.changeStatus(id, dto.status); }
}
