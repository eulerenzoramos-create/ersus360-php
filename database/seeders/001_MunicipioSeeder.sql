-- Seeder 001: Município de Apuí/AM
-- IBGE: 1300144 | Latitude: -7.2003 | Longitude: -59.8914

INSERT INTO municipios (nome, codigo_ibge, estado, populacao, latitude, longitude, ativo)
VALUES ('Apuí', '1300144', 'AM', 21000, -7.2003, -59.8914, 1)
ON DUPLICATE KEY UPDATE
    nome        = VALUES(nome),
    populacao   = VALUES(populacao),
    latitude    = VALUES(latitude),
    longitude   = VALUES(longitude);
