-- CreateTable
CREATE TABLE "operational_expenses" (
    "id" SERIAL NOT NULL,
    "description" TEXT NOT NULL,
    "category" TEXT,
    "amount" DECIMAL(12,2) NOT NULL,
    "expense_date" TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP,
    "created_at" TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT "operational_expenses_pkey" PRIMARY KEY ("id")
);

-- CreateIndex
CREATE INDEX "operational_expenses_expense_date_idx" ON "operational_expenses"("expense_date");
