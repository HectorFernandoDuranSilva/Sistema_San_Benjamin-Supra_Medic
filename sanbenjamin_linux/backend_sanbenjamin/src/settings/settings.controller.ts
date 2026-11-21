import { Body, Controller, Get, Post, UseGuards } from '@nestjs/common';
import { ApiBearerAuth, ApiTags } from '@nestjs/swagger';
import { JwtAuthGuard } from '../auth/jwt-auth.guard';
import { Roles } from '../auth/roles.decorator';
import { RolesGuard } from '../auth/roles.guard';
import { SettingDto } from './dto/setting.dto';
import { SettingsService } from './settings.service';
@ApiTags('settings') @ApiBearerAuth() @UseGuards(JwtAuthGuard, RolesGuard) @Roles('ADMIN') @Controller('settings')
export class SettingsController { constructor(private readonly service: SettingsService) {} @Get() list() { return this.service.list(); } @Post() save(@Body() dto: SettingDto) { return this.service.save(dto); } }
