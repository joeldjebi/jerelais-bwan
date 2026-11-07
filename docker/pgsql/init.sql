-- Extensions nécessaires à JE RELAIS, plus une base dédiée aux tests.
CREATE EXTENSION IF NOT EXISTS postgis;
CREATE EXTENSION IF NOT EXISTS pgcrypto;

CREATE DATABASE jerelais_test OWNER jerelais;
\connect jerelais_test
CREATE EXTENSION IF NOT EXISTS postgis;
CREATE EXTENSION IF NOT EXISTS pgcrypto;
