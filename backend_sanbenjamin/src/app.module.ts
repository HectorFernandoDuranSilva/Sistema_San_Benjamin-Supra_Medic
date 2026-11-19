import { Module } from '@nestjs/common';
import { ConfigModule } from '@nestjs/config';
import { PrismaModule } from './prisma/prisma.module';
import { AuthModule } from './auth/auth.module';
import { CategoriesModule } from './categories/categories.module';
import { ProductsModule } from './products/products.module';
import { CustomersModule } from './customers/customers.module';
import { SuppliersModule } from './suppliers/suppliers.module';
import { SalesModule } from './sales/sales.module';
import { PurchasesModule } from './purchases/purchases.module';
import { InventoryModule } from './inventory/inventory.module';
import { ReportsModule } from './reports/reports.module';
import { QuotationsModule } from './quotations/quotations.module';
import { PaymentsModule } from './payments/payments.module';
import { AuditModule } from './audit/audit.module';
import { InstallmentsModule } from './installments/installments.module';
import { UsersModule } from './users/users.module';
import { SettingsModule } from './settings/settings.module';
import { ExpensesModule } from './expenses/expenses.module';
import { CustomerHistoryModule } from './customer-history/customer-history.module';
import { DocumentsModule } from './documents/documents.module';

@Module({
  imports: [ConfigModule.forRoot({ isGlobal: true }), PrismaModule, AuthModule, CategoriesModule, ProductsModule, CustomersModule, SuppliersModule, SalesModule, PurchasesModule, InventoryModule, ReportsModule, QuotationsModule, PaymentsModule, AuditModule, InstallmentsModule, UsersModule, SettingsModule, ExpensesModule, CustomerHistoryModule, DocumentsModule],
})
export class AppModule {}
