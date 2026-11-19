import { IsDateString, IsNumber, Min } from 'class-validator';
export class InstallmentDto {
  @IsNumber() @Min(0.01) amount!: number;
  @IsDateString() dueDate!: string;
}
