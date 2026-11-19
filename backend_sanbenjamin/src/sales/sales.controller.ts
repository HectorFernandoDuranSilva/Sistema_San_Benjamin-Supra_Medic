import { Body, Controller, Get, Param, ParseIntPipe, Post, Put, Request, UseGuards } from '@nestjs/common';
import { ApiBearerAuth, ApiTags } from '@nestjs/swagger';
import { JwtAuthGuard } from '../auth/jwt-auth.guard';
import { SaleDto } from './dto/sale.dto';
import { SalesService } from './sales.service';
@ApiTags('sales') @ApiBearerAuth() @UseGuards(JwtAuthGuard) @Controller('sales')
export class SalesController {
  constructor(private readonly service: SalesService) {}
  @Get() findAll() { return this.service.findAll(); }
  @Post() create(@Body() dto: SaleDto, @Request() request: { user: { id: number } }) { return this.service.create(dto, request.user.id); }
  @Post(':id/cancel') cancel(@Param('id', ParseIntPipe) id: number, @Request() request: { user: { id: number } }) { return this.service.cancel(id, request.user.id); }
  @Put(':id') update(@Param('id', ParseIntPipe) id: number, @Body() dto: SaleDto, @Request() request: { user: { id: number } }) { return this.service.update(id, dto, request.user.id); }
}
