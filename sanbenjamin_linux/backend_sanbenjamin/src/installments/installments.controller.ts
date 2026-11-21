import { Body, Controller, Get, Param, ParseIntPipe, Post, UseGuards } from '@nestjs/common';
import { ApiBearerAuth, ApiTags } from '@nestjs/swagger';
import { JwtAuthGuard } from '../auth/jwt-auth.guard';
import { InstallmentDto } from './dto/installment.dto';
import { InstallmentStatusDto } from './dto/installment-status.dto';
import { InstallmentsService } from './installments.service';
@ApiTags('installments') @ApiBearerAuth() @UseGuards(JwtAuthGuard) @Controller('installments')
export class InstallmentsController {
  constructor(private readonly service: InstallmentsService) {}
  @Get() list() { return this.service.list(); }
  @Post('sales/:id') forSale(@Param('id', ParseIntPipe) id: number, @Body() dto: InstallmentDto) { return this.service.forSale(id, dto); }
  @Post('purchases/:id') forPurchase(@Param('id', ParseIntPipe) id: number, @Body() dto: InstallmentDto) { return this.service.forPurchase(id, dto); }
  @Post(':id/status') changeStatus(@Param('id', ParseIntPipe) id: number, @Body() dto: InstallmentStatusDto) { return this.service.changeStatus(id, dto.status); }
}
