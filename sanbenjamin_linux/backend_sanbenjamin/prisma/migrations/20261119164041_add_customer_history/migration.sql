-- CreateTable
CREATE TABLE "customer_account_payments" (
    "id" SERIAL NOT NULL,
    "amount" DECIMAL(12,2) NOT NULL,
    "type" "PaymentType" NOT NULL DEFAULT 'CASH',
    "reference" TEXT,
    "customer_id" INTEGER NOT NULL,
    "created_at" TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT "customer_account_payments_pkey" PRIMARY KEY ("id")
);

-- CreateIndex
CREATE INDEX "customer_account_payments_customer_id_created_at_idx" ON "customer_account_payments"("customer_id", "created_at");

-- AddForeignKey
ALTER TABLE "customer_account_payments" ADD CONSTRAINT "customer_account_payments_customer_id_fkey" FOREIGN KEY ("customer_id") REFERENCES "customers"("id") ON DELETE RESTRICT ON UPDATE CASCADE;
