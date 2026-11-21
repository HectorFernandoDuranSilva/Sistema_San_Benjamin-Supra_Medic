const { PrismaClient } = require('@prisma/client');
const bcrypt = require('bcrypt');

const prisma = new PrismaClient();

async function main() {
  const passwordHash = await bcrypt.hash('hector123', 10);
  const user = await prisma.user.upsert({
    where: { username: 'hector' },
    update: { passwordHash, name: 'Hector Fernando Duran Silva', active: true },
    create: { username: 'hector', passwordHash, name: 'Hector Fernando Duran Silva', role: 'ADMIN' },
  });
  const category = await prisma.category.upsert({ where: { name: 'General' }, update: {}, create: { name: 'General', description: 'Categoría inicial' } });
  await prisma.product.upsert({
    where: { code: 'DEMO-001' },
    update: {},
    create: { code: 'DEMO-001', name: 'Producto de demostración', categoryId: category.id, salePrice: 100, purchaseCost: 70, minStock: 5 },
  });
  console.log(`Seed completado para ${user.username}`);
}

main().finally(() => prisma.$disconnect());
