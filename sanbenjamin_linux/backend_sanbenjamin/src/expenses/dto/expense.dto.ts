import { IsDateString, IsNumber, IsOptional, IsString, Min } from 'class-validator';
export class ExpenseDto { @IsString() description!: string; @IsOptional() @IsString() category?: string; @IsNumber() @Min(0.01) amount!: number; @IsOptional() @IsDateString() expenseDate?: string; }
