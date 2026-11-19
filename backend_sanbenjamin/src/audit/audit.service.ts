import { Injectable } from '@nestjs/common';
import { PrismaService } from '../prisma/prisma.service';
@Injectable()
export class AuditService {
  constructor(private readonly prisma: PrismaService) {}
  list() { return this.prisma.auditEvent.findMany({ include: { user: { select: { username: true, name: true } } }, orderBy: { createdAt: 'desc' }, take: 100 }); }
  record(action: string, entity: string, entityId?: number, details?: string, userId?: number) { return this.prisma.auditEvent.create({ data: { action, entity, entityId, details, userId } }); }
}
