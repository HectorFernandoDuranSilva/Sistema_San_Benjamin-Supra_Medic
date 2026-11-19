import { Body, Controller, Get, Post, UseGuards } from '@nestjs/common';
import { ApiBearerAuth, ApiTags } from '@nestjs/swagger';
import { JwtAuthGuard } from '../auth/jwt-auth.guard';
import { InventoryService } from './inventory.service';
import { LotDto, MovementDto } from './dto/lot.dto';
@ApiTags('inventory') @ApiBearerAuth() @UseGuards(JwtAuthGuard) @Controller('inventory')
export class InventoryController {
  constructor(private readonly service: InventoryService) {}
  @Get() findAll() { return this.service.products(); }
  @Get('lots') lots() { return this.service.lots(); }
  @Get('movements') movements() { return this.service.movements(); }
  @Post('lots') createLot(@Body() dto: LotDto) { return this.service.createLot(dto); }
  @Post('adjust') adjust(@Body() dto: MovementDto) { return this.service.adjust(dto); }
}
