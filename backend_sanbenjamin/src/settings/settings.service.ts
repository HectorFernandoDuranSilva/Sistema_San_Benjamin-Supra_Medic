import { Injectable } from '@nestjs/common';
import { PrismaService } from '../prisma/prisma.service';
import { SettingDto } from './dto/setting.dto';
@Injectable()
export class SettingsService { constructor(private readonly prisma: PrismaService) {} list() { return this.prisma.appSetting.findMany({ orderBy: { key: 'asc' } }); } save(dto: SettingDto) { return this.prisma.appSetting.upsert({ where: { key: dto.key }, update: { value: dto.value }, create: dto }); } }
