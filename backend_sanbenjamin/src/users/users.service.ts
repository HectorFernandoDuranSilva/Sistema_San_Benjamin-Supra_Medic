import { ConflictException, Injectable, NotFoundException } from '@nestjs/common';
import * as bcrypt from 'bcrypt';
import { PrismaService } from '../prisma/prisma.service';
import { UserDto } from './dto/user.dto';
@Injectable()
export class UsersService {
  constructor(private readonly prisma: PrismaService) {}
  list() { return this.prisma.user.findMany({ select: { id: true, name: true, username: true, role: true, active: true, twoFactorEnabled: true, createdAt: true }, orderBy: { name: 'asc' } }); }
  async create(dto: UserDto) { try { return await this.prisma.user.create({ data: { name: dto.name, username: dto.username, passwordHash: await bcrypt.hash(dto.password ?? 'cambiar123', 10), role: dto.role, active: dto.active ?? true }, select: { id: true, name: true, username: true, role: true, active: true } }); } catch (error) { if ((error as { code?: string }).code === 'P2002') throw new ConflictException('El usuario ya existe'); throw error; } }
  async update(id: number, dto: UserDto) { const user = await this.prisma.user.findUnique({ where: { id } }); if (!user) throw new NotFoundException('Usuario no encontrado'); return this.prisma.user.update({ where: { id }, data: { name: dto.name, username: dto.username, role: dto.role, active: dto.active, ...(dto.password ? { passwordHash: await bcrypt.hash(dto.password, 10) } : {}) }, select: { id: true, name: true, username: true, role: true, active: true } }); }
  async remove(id: number) { await this.update(id, { name: '', username: '', role: 'OPERATOR', active: false } as UserDto); return { success: true }; }
}
