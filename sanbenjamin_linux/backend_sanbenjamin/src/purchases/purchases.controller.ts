import { Body, Controller, Get, Param, ParseIntPipe, Post, Put, Request, UseGuards } from '@nestjs/common';
import { ApiBearerAuth, ApiTags } from '@nestjs/swagger';
import { JwtAuthGuard } from '../auth/jwt-auth.guard';
import { PurchaseDto } from './dto/purchase.dto';
import { PurchasesService } from './purchases.service';
@ApiTags('purchases') @ApiBearerAuth() @UseGuards(JwtAuthGuard) @Controller('purchases')
export class PurchasesController {
  constructor(private readonly service: PurchasesService) {}
  @Get() findAll() { return this.service.findAll(); }
  @Post() create(@Body() dto: PurchaseDto, @Request() request: { user: { id: number } }) { return this.service.create(dto, request.user.id); }
  @Post(':id/cancel') cancel(@Param('id', ParseIntPipe) id: number, @Request() request: { user: { id: number } }) { return this.service.cancel(id, request.user.id); }
  @Put(':id') update(@Param('id', ParseIntPipe) id: number, @Body() dto: PurchaseDto, @Request() request: { user: { id: number } }) { return this.service.update(id, dto, request.user.id); }
}
