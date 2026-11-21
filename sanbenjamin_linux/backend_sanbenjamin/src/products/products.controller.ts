import { Body, Controller, Delete, Get, Param, ParseIntPipe, Post, Put, UseGuards } from '@nestjs/common';
import { ApiBearerAuth, ApiTags } from '@nestjs/swagger';
import { JwtAuthGuard } from '../auth/jwt-auth.guard';
import { ProductDto } from './dto/product.dto';
import { ProductsService } from './products.service';

@ApiTags('products')
@ApiBearerAuth()
@UseGuards(JwtAuthGuard)
@Controller('products')
export class ProductsController {
  constructor(private readonly products: ProductsService) {}
  @Get() findAll() { return this.products.findAll(); }
  @Get(':id') findOne(@Param('id', ParseIntPipe) id: number) { return this.products.findOne(id); }
  @Post() create(@Body() dto: ProductDto) { return this.products.create(dto); }
  @Put(':id') update(@Param('id', ParseIntPipe) id: number, @Body() dto: ProductDto) { return this.products.update(id, dto); }
  @Delete(':id') remove(@Param('id', ParseIntPipe) id: number) { return this.products.remove(id); }
}
