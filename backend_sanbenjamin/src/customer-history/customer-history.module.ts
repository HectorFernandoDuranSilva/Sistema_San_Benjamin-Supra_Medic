import { Module } from '@nestjs/common';
import { CustomerHistoryController } from './customer-history.controller';
import { CustomerHistoryService } from './customer-history.service';
@Module({ controllers: [CustomerHistoryController], providers: [CustomerHistoryService] }) export class CustomerHistoryModule {}
