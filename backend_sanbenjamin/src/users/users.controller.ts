import { Body, Controller, Get, Param, ParseIntPipe, Post, Put, Delete, UseGuards } from '@nestjs/common';
import { ApiBearerAuth, ApiTags } from '@nestjs/swagger';
import { JwtAuthGuard } from '../auth/jwt-auth.guard';
import { Roles } from '../auth/roles.decorator';
import { RolesGuard } from '../auth/roles.guard';
import { UserDto } from './dto/user.dto';
import { UsersService } from './users.service';
@ApiTags('users') @ApiBearerAuth() @UseGuards(JwtAuthGuard, RolesGuard) @Roles('ADMIN') @Controller('users')
export class UsersController { constructor(private readonly service: UsersService) {} @Get() list() { return this.service.list(); } @Post() create(@Body() dto: UserDto) { return this.service.create(dto); } @Put(':id') update(@Param('id', ParseIntPipe) id: number, @Body() dto: UserDto) { return this.service.update(id, dto); } @Delete(':id') remove(@Param('id', ParseIntPipe) id: number) { return this.service.remove(id); } }
