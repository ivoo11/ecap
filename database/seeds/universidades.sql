INSERT INTO universidades
    (nombre, nombre_corto, tipo, provincia, activa)
VALUES

/* =========================================================
   PÚBLICAS
   ========================================================= */

('Universidad de Buenos Aires', 'UBA', 'publica', 'Ciudad Autónoma de Buenos Aires', 1),

('Universidad Nacional de Avellaneda', 'UNDAV', 'publica', 'Buenos Aires', 1),

('Universidad Nacional de Córdoba', 'UNC', 'publica', 'Córdoba', 1),

('Universidad Nacional de Cuyo', 'UNCUYO', 'publica', 'Mendoza', 1),

('Universidad Nacional de José C. Paz', 'UNPAZ', 'publica', 'Buenos Aires', 1),

('Universidad Nacional de Jujuy', 'UNJu', 'publica', 'Jujuy', 1),

('Universidad Nacional de La Matanza', 'UNLaM', 'publica', 'Buenos Aires', 1),

('Universidad Nacional de La Pampa', 'UNLPam', 'publica', 'La Pampa', 1),

('Universidad Nacional de La Plata', 'UNLP', 'publica', 'Buenos Aires', 1),

('Universidad Nacional de La Rioja', 'UNLaR', 'publica', 'La Rioja', 1),

('Universidad Nacional de Lomas de Zamora', 'UNLZ', 'publica', 'Buenos Aires', 1),

('Universidad Nacional de Mar del Plata', 'UNMdP', 'publica', 'Buenos Aires', 1),

('Universidad Nacional de Río Cuarto', 'UNRC', 'publica', 'Córdoba', 1),

('Universidad Nacional de Río Negro', 'UNRN', 'publica', 'Río Negro', 1),

('Universidad Nacional de Rosario', 'UNR', 'publica', 'Santa Fe', 1),

('Universidad Nacional de San Juan', 'UNSJ', 'publica', 'San Juan', 1),

('Universidad Nacional de San Luis', 'UNSL', 'publica', 'San Luis', 1),

('Universidad Nacional de Tucumán', 'UNT', 'publica', 'Tucumán', 1),

('Universidad Nacional del Centro de la Provincia de Buenos Aires', 'UNICEN', 'publica', 'Buenos Aires', 1),

('Universidad Nacional del Comahue', 'UNCo', 'publica', 'Neuquén / Río Negro', 1),

('Universidad Nacional del Litoral', 'UNL', 'publica', 'Santa Fe', 1),

('Universidad Nacional del Nordeste', 'UNNE', 'publica', 'Corrientes / Chaco', 1),

('Universidad Nacional del Noroeste de la Provincia de Buenos Aires', 'UNNOBA', 'publica', 'Buenos Aires', 1),

('Universidad Nacional de la Patagonia San Juan Bosco', 'UNPSJB', 'publica', 'Chubut', 1),

('Universidad Nacional del Sur', 'UNS', 'publica', 'Buenos Aires', 1),

('Instituto Universitario de la Policía Federal Argentina', 'IUPFA', 'publica', 'Ciudad Autónoma de Buenos Aires', 1),


/* =========================================================
   PRIVADAS
   ========================================================= */

('Universidad Abierta Interamericana', 'UAI', 'privada', 'Ciudad Autónoma de Buenos Aires', 1),

('Universidad Argentina de la Empresa', 'UADE', 'privada', 'Ciudad Autónoma de Buenos Aires', 1),

('Universidad Austral', 'UA', 'privada', 'Buenos Aires', 1),

('Universidad Blas Pascal', 'UBP', 'privada', 'Córdoba', 1),

('Pontificia Universidad Católica Argentina Santa María de los Buenos Aires', 'UCA', 'privada', 'Ciudad Autónoma de Buenos Aires', 1),

('Universidad Católica de Córdoba', 'UCC', 'privada', 'Córdoba', 1),

('Universidad Católica de Cuyo', 'UCCuyo', 'privada', 'San Juan', 1),

('Universidad Católica de La Plata', 'UCALP', 'privada', 'Buenos Aires', 1),

('Universidad Católica de Salta', 'UCASAL', 'privada', 'Salta', 1),

('Universidad Católica de Santa Fe', 'UCSF', 'privada', 'Santa Fe', 1),

('Universidad Champagnat', 'UCH', 'privada', 'Mendoza', 1),

('Universidad de Belgrano', 'UB', 'privada', 'Ciudad Autónoma de Buenos Aires', 1),

('Universidad de Ciencias Empresariales y Sociales', 'UCES', 'privada', 'Ciudad Autónoma de Buenos Aires', 1),

('Universidad de Congreso', 'UC', 'privada', 'Mendoza', 1),

('Universidad de Flores', 'UFLO', 'privada', 'Ciudad Autónoma de Buenos Aires', 1),

('Universidad de la Cuenca del Plata', 'UCP', 'privada', 'Corrientes', 1),

('Universidad de la Marina Mercante', 'UdeMM', 'privada', 'Ciudad Autónoma de Buenos Aires', 1),

('Universidad de Mendoza', 'UM', 'privada', 'Mendoza', 1),

('Universidad de Morón', 'UM', 'privada', 'Buenos Aires', 1),

('Universidad de Palermo', 'UP', 'privada', 'Ciudad Autónoma de Buenos Aires', 1),

('Universidad de San Andrés', 'UdeSA', 'privada', 'Buenos Aires', 1),

('Universidad de San Isidro Dr. Plácido Marín', 'USI', 'privada', 'Buenos Aires', 1),

('Universidad de San Pablo-Tucumán', 'USP-T', 'privada', 'Tucumán', 1),

('Universidad del Aconcagua', 'UDA', 'privada', 'Mendoza', 1),

('Universidad del CEMA', 'UCEMA', 'privada', 'Ciudad Autónoma de Buenos Aires', 1),

('Universidad del Centro Educativo Latinoamericano', 'UCEL', 'privada', 'Santa Fe', 1),

('Universidad del Este', 'UDE', 'privada', 'Buenos Aires', 1),

('Universidad del Museo Social Argentino', 'UMSA', 'privada', 'Ciudad Autónoma de Buenos Aires', 1),

('Universidad del Norte Santo Tomás de Aquino', 'UNSTA', 'privada', 'Tucumán', 1),

('Universidad del Salvador', 'USAL', 'privada', 'Ciudad Autónoma de Buenos Aires', 1),

('Universidad Empresarial Siglo 21', 'UES21', 'privada', 'Córdoba', 1),

('Universidad FASTA', 'UFASTA', 'privada', 'Buenos Aires', 1),

('Universidad Gastón Dachary', 'UGD', 'privada', 'Misiones', 1),

('Universidad Kennedy', 'UK', 'privada', 'Ciudad Autónoma de Buenos Aires', 1),

('Universidad Maimónides', 'UMAI', 'privada', 'Ciudad Autónoma de Buenos Aires', 1),

('Universidad Salesiana', 'UNISAL', 'privada', 'Buenos Aires', 1),

('Universidad Torcuato Di Tella', 'UTDT', 'privada', 'Ciudad Autónoma de Buenos Aires', 1)

ON DUPLICATE KEY UPDATE
    nombre_corto = VALUES(nombre_corto),
    tipo = VALUES(tipo),
    provincia = VALUES(provincia),
    activa = VALUES(activa);