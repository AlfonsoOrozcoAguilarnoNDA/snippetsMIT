-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Servidor: localhost:3306
-- Tiempo de generación: 29-04-2026 a las 09:09:11
-- Versión del servidor: 8.0.46
-- Versión de PHP: 8.4.20

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

CREATE TABLE `log_indexado` (
  `id` int NOT NULL,
  `dominio` varchar(100) DEFAULT NULL,
  `ip_remota` varchar(45) DEFAULT NULL,
  `fecha_acceso` datetime DEFAULT NULL,
  `hostname` varchar(255) DEFAULT NULL,
  `metodo` varchar(10) DEFAULT NULL,
  `recurso` text,
  `query_string` text,
  `http_status` int DEFAULT NULL,
  `bytes` int DEFAULT NULL,
  `user_agent` text,
  `ipbanned` varchar(3) DEFAULT 'NO'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `log_indexado`
--
ALTER TABLE `log_indexado`
  ADD PRIMARY KEY (`id`),
  ADD KEY `ip_remota` (`ip_remota`),
  ADD KEY `ipbanned` (`ipbanned`),
  ADD KEY `dominio` (`dominio`),
  ADD KEY `fecha_acceso` (`fecha_acceso`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `log_indexado`
--
ALTER TABLE `log_indexado`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;
COMMIT;

--
-- Estructura de tabla para la tabla `master_bans`
--

CREATE TABLE `master_bans` (
  `ip` varchar(45) NOT NULL,
  `fecha_ban` datetime DEFAULT NULL,
  `hostname` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `master_bans`
--
ALTER TABLE `master_bans`
  ADD PRIMARY KEY (`ip`);
COMMIT;
