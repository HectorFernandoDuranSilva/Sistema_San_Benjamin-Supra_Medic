import { Module } from '@nestjs/common';
import { QuotationsController } from './quotations.controller';
import { QuotationsService } from './quotations.service';
import { AuditModule } from '../audit/audit.module';
@Module({ imports: [AuditModule], controllers: [QuotationsController], providers: [QuotationsService] }) export class QuotationsModule {}
