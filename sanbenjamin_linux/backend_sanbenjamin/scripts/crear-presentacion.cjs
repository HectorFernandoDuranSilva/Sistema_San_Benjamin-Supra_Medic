const fs = require('fs');
const path = require('path');
const PDFDocument = require('pdfkit');

const root = path.resolve(__dirname, '..', '..');
const outputDir = path.join(root, 'output', 'pdf');
fs.mkdirSync(outputDir, { recursive: true });
const output = path.join(outputDir, 'lab_sis257_san_benjamin.pdf');
const doc = new PDFDocument({ size: [960, 540], margin: 42, autoFirstPage: false, info: { Title: 'SIS257 - San Benjamin-SupraMedic', Author: 'Hector Fernando Duran Silva' } });
doc.pipe(fs.createWriteStream(output));

const C = { navy: '#17324D', blue: '#67BED9', sky: '#EAF7FB', ink: '#26384A', muted: '#55708C', white: '#FFFFFF', line: '#CDE5EE', green: '#2E9B70', orange: '#E48A3A' };
const arial = 'C:/Windows/Fonts/arial.ttf';
const arialBold = 'C:/Windows/Fonts/arialbd.ttf';
function base(title, kicker) { doc.addPage(); doc.rect(0,0,960,540).fill('#F6F9FC'); doc.rect(0,0,960,20).fill(C.blue); doc.font(arialBold).fontSize(10).fillColor(C.blue).text(kicker.toUpperCase(),42,42); doc.font(arialBold).fontSize(27).fillColor(C.navy).text(title,42,62); doc.moveTo(42,104).lineTo(918,104).strokeColor(C.line).stroke(); }
function footer(n) { doc.font(arial).fontSize(9).fillColor(C.muted).text('SIS257 · San Benjamin-SupraMedic',42,486); doc.text(String(n).padStart(2,'0'),890,486,{align:'right',width:28}); }
function text(t,x,y,w,size=16,color=C.ink,opts={}) { doc.font(opts.bold?arialBold:arial).fontSize(size).fillColor(color).text(t,x,y,{width:w,lineGap:opts.lineGap||3,align:opts.align||'left'}); }
function card(x,y,w,h,title,body,color=C.sky) { doc.roundedRect(x,y,w,h,10).fill(color).strokeColor(C.line).stroke(); text(title,x+16,y+14,w-32,15,C.navy,{bold:true}); text(body,x+16,y+42,w-32,13,C.ink); }
function pill(x,y,label,color=C.green) { doc.roundedRect(x,y,112,25,12).fill(color); text(label,x,y+7,112,10,C.white,{bold:true,align:'center'}); }
function box(x,y,w,h,label,fill=C.sky) { doc.roundedRect(x,y,w,h,8).fill(fill).strokeColor(C.blue).stroke(); text(label,x+6,y+h/2-7,w-12,12,C.navy,{bold:true,align:'center'}); }
function arrow(x1,y1,x2,y2) { doc.moveTo(x1,y1).lineTo(x2,y2).strokeColor(C.blue).lineWidth(2).stroke(); doc.moveTo(x2,y2).lineTo(x2-7,y2-4).lineTo(x2-7,y2+4).closePath().fill(C.blue); }

// 1. Cover
doc.addPage(); doc.rect(0,0,960,540).fill('#F6F9FC'); doc.rect(0,0,960,24).fill(C.blue); doc.rect(42,52,5,372).fill(C.blue); text('LABORATORIO SIS257',78,72,500,15,C.blue,{bold:true}); text('San Benjamin-SupraMedic',78,126,720,42,C.navy,{bold:true}); text('Sistema de gestión de inventarios, compras y ventas',80,188,650,22,C.muted); doc.roundedRect(80,275,470,118,8).fill(C.white).strokeColor(C.line).stroke(); text('Integrante',102,298,150,12,C.muted,{bold:true}); text('Hector Fernando Duran Silva',102,320,380,18,C.navy,{bold:true}); text('Tema: gestión comercial para San Benjamin',102,353,380,13,C.ink); text('DEMOSTRACIÓN FUNCIONAL · 2026',80,445,330,12,C.green,{bold:true}); footer(1);

// 2. Business
base('Descripción del negocio','01 · Contexto');
text('San Benjamin es un negocio dedicado a la comercialización de productos. El sistema centraliza el catálogo, las existencias y las operaciones de compra y venta en una sola interfaz.',42,132,540,19,C.ink,{lineGap:6});
card(42,260,265,142,'Catálogo','Productos y categorías con precio, costo, stock mínimo y estado de disponibilidad.');
card(330,260,265,142,'Operación','Compras que aumentan stock y ventas que lo descuentan automáticamente.');
card(618,260,265,142,'Control','Lotes, vencimientos, kardex, pagos, cuotas, gastos y auditoría.');
text('Tecnología implementada',42,442,220,13,C.blue,{bold:true}); text('NestJS · Prisma · PostgreSQL · JWT · Vue 3 · Axios · Bootstrap',240,442,600,14,C.navy,{bold:true}); footer(2);

// 3. Problem
base('Problemática y solución','02 · Modelo de negocio');
card(42,132,385,276,'Situación que originó el sistema','La operación del negocio se sostenía en el archivo Excel “Sistemas de inventarios Compras Ventas.xlsm”. El archivo concentraba productos, precios, compras, ventas, clientes, proveedores, recibos y reportes en varias hojas, algunas ocultas.\n\nEl stock no era un dato aislado: se obtenía de entradas, salidas y ajustes. Esto hacía difícil mantener trazabilidad, evitar errores y consultar el estado real del inventario.', '#FFF5EA');
card(533,132,385,276,'Respuesta del sistema','La aplicación web conserva la lógica del negocio y registra los movimientos en PostgreSQL. Las compras incrementan existencias, las ventas las descuentan y cada documento conserva sus detalles, pagos, cuotas y estado.\n\nAsí se reemplaza la edición manual de existencias por un flujo consultable y demostrable: catálogo → compra → inventario → venta → cobro → reporte.', '#EAF7FB');
text('Decisión de diseño',42,433,160,13,C.blue,{bold:true}); text('Migrar la operación a NestJS + Prisma + PostgreSQL y mantener el diseño y las funcionalidades del sistema original.',210,433,680,15,C.navy,{bold:true}); footer(3);

// 4. ER diagram generated in DBeaver
base('Diagrama entidad-relación','03 · Persistencia');
const er = path.join(outputDir, 'dbeaver-er-diagram.png');
if (fs.existsSync(er)) doc.image(er,42,122,{fit:[876,335],align:'center',valign:'center'});
else text('No se encontró la exportación de DBeaver.',42,170,500,16,C.ink);
text('Diagrama generado en DBeaver sobre PostgreSQL · esquema public · base sis257_sanbenjamin',42,478,876,11,C.muted,{bold:true,align:'center'}); footer(4);

// 5. Access
base('Enlaces y datos de acceso','04 · Ejecución local');
card(42,132,410,230,'Frontend','http://127.0.0.1:5174/\n\nDirectorio: frontend_sanbenjamin\nTecnología: Vue 3 + Axios + Bootstrap', '#EAF7FB');
card(508,132,410,230,'Backend','http://127.0.0.1:3000/api\n\nDirectorio: backend_sanbenjamin\nTecnología: NestJS + Prisma + JWT', '#EAF7FB');
doc.roundedRect(42,392,876,78,10).fill(C.navy); text('Usuario de demostración: hector',62,408,360,15,C.white,{bold:true}); text('Contraseña: hector123',610,408,250,15,C.white,{bold:true,align:'right'}); text('GitHub: https://github.com/HectorFernandoDuranSilva/Sistema_San_Benjamin-Supra_Medic',62,442,820,11,C.white); footer(5);

// 6. Evidence
base('Pruebas de funcionamiento','05 · Evidencias para la demostración');
text('Pruebas ejecutadas',42,128,220,16,C.navy,{bold:true});
const rows = [['Login y dashboard','Correcto','Indicadores cargan desde PostgreSQL'],['Compra y venta','Correcto','Stock actualizado en ambos flujos'],['Edición de documentos','Correcto','Compra y venta editables'],['Recibos','Correcto','Vista previa y PDF disponibles'],['Backend / frontend','Correcto','6 suites, 8 pruebas y builds'],['Consola del navegador','Correcto','Sin errores ni advertencias']];
let y=160; rows.forEach((r,i)=>{doc.rect(42,y,500,28).fill(i%2?'#FFFFFF':'#EAF7FB').strokeColor(C.line).stroke(); text(r[0],54,y+8,165,10,C.navy,{bold:true}); text(r[1],225,y+8,75,10,C.green,{bold:true}); text(r[2],310,y+8,220,9,C.ink); y+=28;});
text('Capturas de pruebas',570,128,300,16,C.navy,{bold:true});
const pg = 'C:/Users/Pc/AppData/Local/Temp/codex-clipboard-94dc3e77-f638-4f6d-9ad6-267e9250e16f.png';
const purchase = path.join(root,'tmp-video-review','video1-20.jpg');
const purchaseLot = path.join(root,'tmp-video-review','video1-0.jpg');
const receipt = path.join(root,'output','pdf','verificacion-recibo-lotes-1.png');
const shots = [[pg,'PostgreSQL / pgAdmin'],[purchase,'Registro de compra'],[purchaseLot,'Compra con lote'],[receipt,'Recibo y vencimiento']];
shots.forEach((s,i)=>{const x=570+(i%2)*174; const yy=160+Math.floor(i/2)*142; text(s[1],x,yy,164,9,C.navy,{bold:true}); if(fs.existsSync(s[0])) doc.image(s[0],x,yy+18,{fit:[164,100],align:'center',valign:'center'});}); footer(6);

doc.end();
console.log(output);
