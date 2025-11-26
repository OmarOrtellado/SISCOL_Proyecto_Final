-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 26-11-2025 a las 20:40:05
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `proysiscol`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `analitico`
--

CREATE TABLE `analitico` (
  `id` int(11) NOT NULL,
  `id_estudiante` int(11) NOT NULL,
  `id_materia` int(11) NOT NULL,
  `cursada` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = sí, 0 = no',
  `regular` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = sí, 0 = no',
  `inscripto_para_final` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = Inscripto para final, 0 = No',
  `aprobada` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = sí, 0 = no',
  `calificacion_final` int(11) DEFAULT NULL COMMENT 'Nota final (1-10)',
  `activo` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'para mostrar el panel con las materias'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `analitico`
--

INSERT INTO `analitico` (`id`, `id_estudiante`, `id_materia`, `cursada`, `regular`, `inscripto_para_final`, `aprobada`, `calificacion_final`, `activo`) VALUES
(1, 1, 4, 1, 1, 1, 1, 10, 1),
(2, 1, 5, 0, 0, 0, 0, NULL, 1),
(3, 1, 6, 0, 0, 0, 0, NULL, 1),
(4, 1, 8, 0, 0, 0, 0, NULL, 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `asignaciones`
--

CREATE TABLE `asignaciones` (
  `id` int(11) NOT NULL,
  `id_profesor` int(11) NOT NULL,
  `id_materia` int(11) NOT NULL,
  `anio_lectivo` year(4) NOT NULL DEFAULT year(curdate()),
  `activo` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `asignaciones`
--

INSERT INTO `asignaciones` (`id`, `id_profesor`, `id_materia`, `anio_lectivo`, `activo`) VALUES
(1, 1, 4, '2025', 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `asistencias`
--

CREATE TABLE `asistencias` (
  `id` int(11) NOT NULL COMMENT 'ID único de la asistencia',
  `id_usuario` int(11) NOT NULL,
  `id_materia` int(11) NOT NULL COMMENT 'ID de la materia para la asistencia',
  `fecha` date NOT NULL,
  `presente` tinyint(1) NOT NULL,
  `ausente` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = Ausente, 0 = No Ausente (puede ser Presente o No tomada)'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `asistencias`
--

INSERT INTO `asistencias` (`id`, `id_usuario`, `id_materia`, `fecha`, `presente`, `ausente`) VALUES
(1, 1, 4, '2025-11-13', 1, 0);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `auditoria`
--

CREATE TABLE `auditoria` (
  `id` bigint(20) NOT NULL,
  `id_usuario` int(11) NOT NULL COMMENT 'ID del usuario en su tabla respectiva',
  `tipo_usuario` enum('estudiante','profesor','directivo','secretario','super_usuario') NOT NULL COMMENT 'Tabla de origen del usuario',
  `usuario_nombre` varchar(255) NOT NULL COMMENT 'Nombre del usuario en el momento del evento',
  `accion` enum('CREATE','READ','UPDATE','DELETE','LOGIN','LOGOUT','FAILED_LOGIN','EXPORT','CONFIG_CHANGE','REGULARIZAR_ESTUDIANTE','TOMAR_ASISTENCIA','INSCRIBIR_FINAL') NOT NULL COMMENT 'Acción estandarizada',
  `objeto_afectado` varchar(100) NOT NULL COMMENT 'Tabla o entidad afectada (ej: estudiante, asistencias, analitico)',
  `id_objeto` int(11) DEFAULT NULL COMMENT 'ID del registro afectado (ej: id de un estudiante, id de una asistencia)',
  `campo_modificado` varchar(100) DEFAULT NULL COMMENT 'Nombre del campo que cambió (solo para UPDATE)',
  `valor_anterior` text DEFAULT NULL COMMENT 'Valor antes del cambio (JSON si es complejo)',
  `valor_nuevo` text DEFAULT NULL COMMENT 'Valor después del cambio (JSON si es complejo)',
  `ip_origen` varchar(45) DEFAULT NULL COMMENT 'IP del cliente',
  `user_agent` text DEFAULT NULL COMMENT 'Información del navegador/dispositivo',
  `resultado` enum('EXITO','FALLIDO','RECHAZADO') NOT NULL DEFAULT 'EXITO' COMMENT 'Resultado de la operación',
  `motivo_fallo` varchar(255) DEFAULT NULL COMMENT 'Detalle del error si falló',
  `fecha_hora` datetime(6) NOT NULL DEFAULT current_timestamp(6) COMMENT 'Fecha y hora con microsegundos',
  `session_id` varchar(255) DEFAULT NULL COMMENT 'ID de sesión del usuario'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `auditoria`
--

INSERT INTO `auditoria` (`id`, `id_usuario`, `tipo_usuario`, `usuario_nombre`, `accion`, `objeto_afectado`, `id_objeto`, `campo_modificado`, `valor_anterior`, `valor_nuevo`, `ip_origen`, `user_agent`, `resultado`, `motivo_fallo`, `fecha_hora`, `session_id`) VALUES
(1, 1, 'estudiante', 'Yanet Cardozo', 'LOGIN', '0', 1, NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 'EXITO', NULL, '2025-11-19 13:22:02.524170', 'iakcpave1avl40edfqln73qr0h'),
(2, 1, '', 'Secretario Ejemplo', 'LOGIN', '0', 1, NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 'EXITO', NULL, '2025-11-19 13:25:10.606557', 'iakcpave1avl40edfqln73qr0h'),
(3, 1, '', 'Secretario Ejemplo', '', '0', NULL, NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 'EXITO', NULL, '2025-11-19 13:33:09.536928', 'iakcpave1avl40edfqln73qr0h'),
(4, 1, '', 'Secretario Ejemplo', '', '0', 5, 'nombre', NULL, 'Profesorado en Lengua y Literatura', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 'EXITO', NULL, '2025-11-19 13:41:18.316027', 'iakcpave1avl40edfqln73qr0h'),
(5, 1, '', 'Secretario Ejemplo', '', '0', 5, 'descripcion', NULL, 'Carrera de 4 años para formar docentes en enseñanza de lengua española y literatura en secundaria y superior. Combina teoría (gramática, teoría literaria, historia literaria argentina y universal) con pedagogía y prácticas.\r\nObjetivos: Fomentar lectura crítica, escritura creativa y análisis textual; innovar en metodologías y materiales didácticos.\r\nEgresados: Docentes en escuelas/universidades, investigadores en lingüística y educación, productores de recursos curriculares.\r\nIdeal para apasionados por la palabra y la educación transformadora.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 'EXITO', NULL, '2025-11-19 13:41:18.318995', 'iakcpave1avl40edfqln73qr0h'),
(6, 1, '', 'Secretario Ejemplo', '', '0', 5, 'duracion', NULL, '4', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 'EXITO', NULL, '2025-11-19 13:41:18.319994', 'iakcpave1avl40edfqln73qr0h'),
(7, 1, '', 'Secretario Ejemplo', '', '0', 5, 'estado', NULL, 'activa', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 'EXITO', NULL, '2025-11-19 13:41:18.320953', 'iakcpave1avl40edfqln73qr0h'),
(8, 1, '', 'Secretario Ejemplo', '', '0', NULL, NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 'EXITO', NULL, '2025-11-19 13:41:21.008516', 'iakcpave1avl40edfqln73qr0h'),
(9, 1, '', 'Secretario Ejemplo', '', '0', 19, 'nombre', NULL, 'Teoría literaria', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 'EXITO', NULL, '2025-11-19 13:45:38.125868', 'iakcpave1avl40edfqln73qr0h'),
(10, 1, '', 'Secretario Ejemplo', '', '0', 19, 'id_carrera', NULL, '4', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 'EXITO', NULL, '2025-11-19 13:45:38.128923', 'iakcpave1avl40edfqln73qr0h'),
(11, 1, '', 'Secretario Ejemplo', '', '0', 19, 'activo', NULL, '1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 'EXITO', NULL, '2025-11-19 13:45:38.134538', 'iakcpave1avl40edfqln73qr0h'),
(12, 1, '', 'Secretario Ejemplo', '', '0', 19, 'año', NULL, '1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 'EXITO', NULL, '2025-11-19 13:45:38.137206', 'iakcpave1avl40edfqln73qr0h'),
(13, 1, '', 'Secretario Ejemplo', '', '0', 20, 'nombre', NULL, 'Teoría literaria', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 'EXITO', NULL, '2025-11-19 13:49:58.877540', 'iakcpave1avl40edfqln73qr0h'),
(14, 1, '', 'Secretario Ejemplo', '', '0', 20, 'id_carrera', NULL, '4', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 'EXITO', NULL, '2025-11-19 13:49:58.879306', 'iakcpave1avl40edfqln73qr0h'),
(15, 1, '', 'Secretario Ejemplo', '', '0', 20, 'activo', NULL, '1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 'EXITO', NULL, '2025-11-19 13:49:58.881551', 'iakcpave1avl40edfqln73qr0h'),
(16, 1, '', 'Secretario Ejemplo', '', '0', 20, 'año', NULL, '1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 'EXITO', NULL, '2025-11-19 13:49:58.883873', 'iakcpave1avl40edfqln73qr0h'),
(17, 1, '', 'Secretario Ejemplo', '', '0', NULL, NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 'EXITO', NULL, '2025-11-19 13:50:00.804926', 'iakcpave1avl40edfqln73qr0h'),
(18, 1, '', 'Secretario Ejemplo', '', '0', NULL, NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 'EXITO', NULL, '2025-11-19 13:59:40.036162', 'iakcpave1avl40edfqln73qr0h'),
(19, 1, '', 'Secretario Ejemplo', '', '0', 3, 'tipo_condicion', NULL, 'regular', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 'EXITO', NULL, '2025-11-19 14:00:41.362365', 'iakcpave1avl40edfqln73qr0h'),
(20, 1, '', 'Secretario Ejemplo', '', '0', 3, 'id_materia', NULL, '18', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 'EXITO', NULL, '2025-11-19 14:00:41.365541', 'iakcpave1avl40edfqln73qr0h'),
(21, 1, '', 'Secretario Ejemplo', '', '0', 3, 'id_materia_correlativa', NULL, '15', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 'EXITO', NULL, '2025-11-19 14:00:41.368659', 'iakcpave1avl40edfqln73qr0h'),
(22, 1, '', 'Secretario Ejemplo', '', '0', NULL, NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 'EXITO', NULL, '2025-11-19 14:22:17.784183', 'iakcpave1avl40edfqln73qr0h'),
(23, 1, 'estudiante', 'Yanet Cardozo', 'LOGOUT', '0', NULL, NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 'EXITO', NULL, '2025-11-26 01:07:04.425017', 'iakcpave1avl40edfqln73qr0h'),
(24, 1, 'estudiante', 'Yanet Cardozo', 'LOGIN', '0', 1, NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 'EXITO', NULL, '2025-11-26 01:08:10.678717', 'iakcpave1avl40edfqln73qr0h'),
(25, 1, 'estudiante', 'Yanet Cardozo', 'LOGOUT', '0', NULL, NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 'EXITO', NULL, '2025-11-26 01:08:13.140029', 'iakcpave1avl40edfqln73qr0h'),
(26, 1, 'estudiante', 'Yanet Cardozo', 'LOGIN', '0', 1, NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 'EXITO', NULL, '2025-11-26 02:08:37.747439', 'iakcpave1avl40edfqln73qr0h'),
(27, 1, 'estudiante', 'Yanet Cardozo', 'LOGOUT', '0', NULL, NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 'EXITO', NULL, '2025-11-26 02:08:58.930212', 'iakcpave1avl40edfqln73qr0h'),
(28, 1, 'super_usuario', 'Admin Principal', 'LOGIN', '0', 1, NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 'EXITO', NULL, '2025-11-26 10:59:04.811829', 'iakcpave1avl40edfqln73qr0h'),
(29, 1, 'super_usuario', 'Admin Principal', 'LOGOUT', '0', NULL, NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 'EXITO', NULL, '2025-11-26 11:18:14.727333', 'iakcpave1avl40edfqln73qr0h'),
(30, 1, '', 'Omar Ortellado', 'LOGIN', '0', 1, NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 'EXITO', NULL, '2025-11-26 11:18:26.178037', 'iakcpave1avl40edfqln73qr0h'),
(31, 1, '', 'Omar Ortellado', 'LOGOUT', '0', NULL, NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 'EXITO', NULL, '2025-11-26 13:44:49.531748', 'iakcpave1avl40edfqln73qr0h'),
(32, 1, 'super_usuario', 'Admin Principal', 'LOGIN', '0', 1, NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 'EXITO', NULL, '2025-11-26 13:47:22.854132', 'iakcpave1avl40edfqln73qr0h');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `carreras`
--

CREATE TABLE `carreras` (
  `id_carrera` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL COMMENT 'Nombre completo de la carrera',
  `descripcion` text DEFAULT NULL COMMENT 'Descripción detallada de la carrera',
  `duracion` int(11) NOT NULL DEFAULT 4 COMMENT 'Duración de la carrera en años',
  `estado` enum('activa','inactiva','suspendida') NOT NULL DEFAULT 'activa' COMMENT 'Estado actual de la carrera',
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Fecha de creación del registro',
  `fecha_modificacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'Fecha de última modificación'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `carreras`
--

INSERT INTO `carreras` (`id_carrera`, `nombre`, `descripcion`, `duracion`, `estado`, `fecha_creacion`, `fecha_modificacion`) VALUES
(1, 'TECNICATURA EN DESARROLLADOR DE SOFTWARE', '\"La Tecnicatura en Desarrollador de Software es un programa orientado a formar profesionales técnicos capaces de diseñar, implementar y mantener soluciones informáticas innovadoras. A lo largo de la carrera, los estudiantes adquieren competencias clave en programación orientada a objetos (con lenguajes como Python, Java y JavaScript), desarrollo web y móvil, gestión de bases de datos (SQL y NoSQL), y metodologías ágiles como Scrum. Con un enfoque práctico y proyectos reales, se prepara al egresado para roles como desarrollador junior, programador de aplicaciones o integrador de sistemas. Esta formación fomenta el pensamiento lógico, la resolución de problemas y la adaptación a tecnologías emergentes, facilitando una rápida inserción laboral en el sector IT. El plan de estudios incluye prácticas profesionales supervisadas y certificaciones opcionales en herramientas como Git y AWS.\"', 4, 'activa', '2025-11-04 16:55:35', '2025-11-07 23:11:44'),
(2, 'TECNICATURA EN DESARROLLADOR DE SOFTWARE 2025', 'nueva carrera', 3, 'activa', '2025-11-07 23:23:50', '2025-11-07 23:23:50'),
(3, 'TECNICATURA SUPERIOR EN ADMINISTRACIÓN DE SISTEMAS Y REDES', 'Tecnicatura Superior en Administración de Sistemas y Redes\r\nLa Tecnicatura Superior en Administración de Sistemas y Redes forma profesionales para diseñar, implementar y gestionar infraestructuras tecnológicas en entornos empresariales. En un mundo digitalizado, esta carrera equipa a los egresados con habilidades para optimizar redes, sistemas y ciberseguridad, asegurando eficiencia y conectividad.\r\nObjetivos\r\nDesarrollar competencias en administración de sistemas operativos, redes y cloud computing, con énfasis en innovación, pensamiento crítico y adaptabilidad a tecnologías emergentes.\r\nDuración y Estructura\r\nDe 3 años (2.480 horas), con módulos teórico-prácticos, prácticas en laboratorios y proyectos integradores. Primer año: fundamentos; segundo: especialización; tercero: seguridad y cloud.\r\nContenidos Principales\r\n\r\nHardware/software y configuración.\r\nRedes LAN/WAN, TCP/IP y diagnóstico.\r\nSistemas Windows/Linux, virtualización.\r\nBases de datos SQL y almacenamiento.\r\nCiberseguridad.', 3, 'activa', '2025-11-13 12:29:44', '2025-11-13 12:29:44'),
(4, 'Profesorado en Lengua y Literatura', 'Carrera de 4 años para formar docentes en enseñanza de lengua española y literatura en secundaria y superior. Combina teoría (gramática, teoría literaria, historia literaria argentina y universal) con pedagogía y prácticas.\r\nObjetivos: Fomentar lectura crítica, escritura creativa y análisis textual; innovar en metodologías y materiales didácticos.\r\nEgresados: Docentes en escuelas/universidades, investigadores en lingüística y educación, productores de recursos curriculares.\r\nIdeal para apasionados por la palabra y la educación transformadora.', 4, 'activa', '2025-11-19 16:37:15', '2025-11-19 16:37:15'),
(5, 'Profesorado en Lengua y Literatura', 'Carrera de 4 años para formar docentes en enseñanza de lengua española y literatura en secundaria y superior. Combina teoría (gramática, teoría literaria, historia literaria argentina y universal) con pedagogía y prácticas.\r\nObjetivos: Fomentar lectura crítica, escritura creativa y análisis textual; innovar en metodologías y materiales didácticos.\r\nEgresados: Docentes en escuelas/universidades, investigadores en lingüística y educación, productores de recursos curriculares.\r\nIdeal para apasionados por la palabra y la educación transformadora.', 4, 'activa', '2025-11-19 16:41:18', '2025-11-19 16:41:18');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `correlatividades`
--

CREATE TABLE `correlatividades` (
  `id` int(11) NOT NULL,
  `id_materia` int(11) NOT NULL COMMENT 'Materia que requiere la correlatividad',
  `id_materia_correlativa` int(11) NOT NULL COMMENT 'Materia que es requisito',
  `tipo_condicion` enum('regular','aprobada') NOT NULL DEFAULT 'aprobada',
  `activo` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `correlatividades`
--

INSERT INTO `correlatividades` (`id`, `id_materia`, `id_materia_correlativa`, `tipo_condicion`, `activo`) VALUES
(1, 6, 5, 'regular', 1),
(2, 8, 4, 'aprobada', 0),
(3, 18, 15, 'regular', 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cursos`
--

CREATE TABLE `cursos` (
  `id_curso` int(11) NOT NULL,
  `nombre_curso` varchar(50) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `anio_lectivo` year(4) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `directivos`
--

CREATE TABLE `directivos` (
  `id_direccion` int(11) NOT NULL,
  `dni` varchar(20) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `apellido` varchar(100) NOT NULL,
  `email` varchar(120) NOT NULL,
  `telefono` varchar(30) DEFAULT NULL,
  `direccion` varchar(255) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `rol_id` int(11) NOT NULL DEFAULT 4,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `fecha_de_alta` date NOT NULL DEFAULT curdate(),
  `numero_legajo` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `directivos`
--

INSERT INTO `directivos` (`id_direccion`, `dni`, `nombre`, `apellido`, `email`, `telefono`, `direccion`, `password_hash`, `rol_id`, `activo`, `fecha_de_alta`, `numero_legajo`) VALUES
(1, '46063748', 'Omar', 'Ortellado', 'elchiconumero35@gmail.com', '03704512143', 'Calle Directiva 789, Córdoba (ficticio)', '$2y$10$TtXspfuqHyuAyLKKquy8qucTiXpD0EJYNMHScG/6IUqT9Y0bnM3ZO', 4, 1, '2025-11-07', 'DIR001');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `estudiante`
--

CREATE TABLE `estudiante` (
  `id` int(11) NOT NULL,
  `dni` varchar(20) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `apellido` varchar(100) NOT NULL,
  `email` varchar(120) NOT NULL,
  `telefono` varchar(30) DEFAULT NULL,
  `direccion` varchar(255) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `rol_id` int(11) NOT NULL DEFAULT 1,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `id_carrera` int(11) DEFAULT NULL COMMENT '1',
  `anio_ingreso` year(4) DEFAULT NULL COMMENT 'Año de ingreso a la carrera',
  `anio_actual` tinyint(1) DEFAULT NULL COMMENT 'Año actual manual (NULL = automático)'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `estudiante`
--

INSERT INTO `estudiante` (`id`, `dni`, `nombre`, `apellido`, `email`, `telefono`, `direccion`, `password_hash`, `rol_id`, `activo`, `creado_en`, `id_carrera`, `anio_ingreso`, `anio_actual`) VALUES
(1, '37109503', 'Yanet', 'Cardozo', 'yanet@prueba.com', '03704512143', 'Av. Principal 456, Córdoba (ficticio)', '$2y$10$UIdeNIKsYFE6tzXKmL8/9eNELWrKb3KGIBhKWYMJd2tYMqqMijN5m', 1, 1, '2025-08-17 08:21:02', 1, '2025', NULL),
(4, '40123456', 'Juan', 'Pérez', 'juan.perez@estudiante.com', '03704601234', 'Barrio Centro, Formosa', '$2y$10$abcdefghijklmnopqrstuvwxyz123456789ABCDEFGHIJ', 1, 1, '2025-11-11 23:03:53', 1, '2025', NULL),
(5, '41234567', 'Sofía', 'García', 'sofia.garcia@estudiante.com', '03704602345', 'Barrio Norte, Formosa', '$2y$10$abcdefghijklmnopqrstuvwxyz123456789ABCDEFGHIJ', 1, 1, '2025-11-11 23:03:53', 1, '2025', NULL),
(6, '42345678', 'Mateo', 'Romero', 'mateo.romero@estudiante.com', '03704603456', 'Barrio Sur, Formosa', '$2y$10$abcdefghijklmnopqrstuvwxyz123456789ABCDEFGHIJ', 1, 1, '2025-11-11 23:03:53', 1, '2024', NULL),
(7, '43456789', 'Valentina', 'Díaz', 'valentina.diaz@estudiante.com', '03704604567', 'Barrio Este, Formosa', '$2y$10$abcdefghijklmnopqrstuvwxyz123456789ABCDEFGHIJ', 1, 1, '2025-11-11 23:03:53', 1, '2024', NULL),
(8, '44567890', 'Lucas', 'Morales', 'lucas.morales@estudiante.com', '03704605678', 'Barrio Oeste, Formosa', '$2y$10$abcdefghijklmnopqrstuvwxyz123456789ABCDEFGHIJ', 1, 1, '2025-11-11 23:03:53', 1, '2023', NULL),
(9, '45678901', 'Camila', 'Silva', 'camila.silva@estudiante.com', '03704606789', 'Av. Fotheringham, Formosa', '$2y$10$abcdefghijklmnopqrstuvwxyz123456789ABCDEFGHIJ', 1, 1, '2025-11-11 23:03:53', 1, '2023', NULL),
(10, '46789012', 'Nicolás', 'Castro', 'nicolas.castro@estudiante.com', '03704607890', 'Barrio San José, Formosa', '$2y$10$abcdefghijklmnopqrstuvwxyz123456789ABCDEFGHIJ', 1, 1, '2025-11-11 23:03:53', 1, '2022', NULL),
(11, '47890123', 'Martina', 'Benítez', 'martina.benitez@estudiante.com', '03704608901', 'Barrio Loma Hermosa, Formosa', '$2y$10$abcdefghijklmnopqrstuvwxyz123456789ABCDEFGHIJ', 1, 1, '2025-11-11 23:03:53', 1, '2025', NULL),
(12, '48901234', 'Tomás', 'Vera', 'tomas.vera@estudiante.com', '03704609012', 'Barrio Villa del Carmen, Formosa', '$2y$10$abcdefghijklmnopqrstuvwxyz123456789ABCDEFGHIJ', 1, 1, '2025-11-11 23:03:53', 1, '2024', NULL),
(13, '49012345', 'Isabella', 'Ojeda', 'isabella.ojeda@estudiante.com', '03704610123', 'Barrio Guadalupe, Formosa', '$2y$10$abcdefghijklmnopqrstuvwxyz123456789ABCDEFGHIJ', 1, 1, '2025-11-11 23:03:53', 1, '2023', NULL),
(14, '50123456', 'Benjamín', 'Acosta', 'benjamin.acosta@estudiante.com', '03704611234', 'Barrio Virgen de Luján, Formosa', '$2y$10$abcdefghijklmnopqrstuvwxyz123456789ABCDEFGHIJ', 1, 1, '2025-11-11 23:03:53', 1, '2025', NULL),
(15, '51234567', 'Emma', 'Ruiz', 'emma.ruiz@estudiante.com', '03704612345', 'Barrio San Agustín, Formosa', '$2y$10$abcdefghijklmnopqrstuvwxyz123456789ABCDEFGHIJ', 1, 1, '2025-11-11 23:03:53', 1, '2024', NULL),
(16, '52345678', 'Santiago', 'Giménez', 'santiago.gimenez@estudiante.com', '03704613456', 'Barrio Liborsi, Formosa', '$2y$10$abcdefghijklmnopqrstuvwxyz123456789ABCDEFGHIJ', 1, 1, '2025-11-11 23:03:53', 1, '2023', NULL),
(17, '53456789', 'Mía', 'Ortiz', 'mia.ortiz@estudiante.com', '03704614567', 'Barrio Juan Domingo Perón, Formosa', '$2y$10$abcdefghijklmnopqrstuvwxyz123456789ABCDEFGHIJ', 1, 1, '2025-11-11 23:03:53', 1, '2022', NULL),
(18, '54567890', 'Lautaro', 'Mendoza', 'lautaro.mendoza@estudiante.com', '03704615678', 'Barrio Simón Bolívar, Formosa', '$2y$10$abcdefghijklmnopqrstuvwxyz123456789ABCDEFGHIJ', 1, 1, '2025-11-11 23:03:53', 1, '2025', NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `materias`
--

CREATE TABLE `materias` (
  `id` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `activo` tinyint(1) DEFAULT 1,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `id_carrera` int(11) NOT NULL COMMENT 'ID de la carrera asociada',
  `fecha_modificacion` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'Fecha de última modificación',
  `año` int(11) NOT NULL DEFAULT 1 COMMENT 'Año de la materia en el plan de estudios (ej: 1, 2, 3)'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `materias`
--

INSERT INTO `materias` (`id`, `nombre`, `activo`, `fecha_creacion`, `id_carrera`, `fecha_modificacion`, `año`) VALUES
(4, 'Ingles Técnico I', 1, '2025-11-07 09:09:54', 1, '2025-11-07 09:09:54', 1),
(5, 'Programación I', 1, '2025-11-07 09:17:48', 1, '2025-11-07 09:17:48', 2),
(6, 'Programación II', 1, '2025-11-07 09:18:08', 1, '2025-11-07 09:18:08', 3),
(8, 'prueba', 1, '2025-11-07 23:12:34', 1, '2025-11-07 23:12:34', 4),
(9, 'Matemática I', 1, '2025-11-11 23:03:53', 1, '2025-11-11 23:03:53', 1),
(10, 'Algoritmos y Estructuras de Datos', 1, '2025-11-11 23:03:53', 1, '2025-11-11 23:03:53', 1),
(11, 'Sistemas Operativos', 1, '2025-11-11 23:03:53', 1, '2025-11-11 23:03:53', 2),
(12, 'Bases de Datos I', 1, '2025-11-11 23:03:53', 1, '2025-11-11 23:03:53', 2),
(13, 'Desarrollo Web', 1, '2025-11-11 23:03:53', 1, '2025-11-11 23:03:53', 2),
(14, 'Bases de Datos II', 1, '2025-11-11 23:03:53', 1, '2025-11-11 23:03:53', 3),
(15, 'Ingeniería de Software', 1, '2025-11-11 23:03:53', 1, '2025-11-11 23:03:53', 3),
(16, 'Redes y Comunicaciones', 1, '2025-11-11 23:03:53', 1, '2025-11-11 23:03:53', 3),
(17, 'Programación Móvil', 1, '2025-11-11 23:03:53', 1, '2025-11-11 23:03:53', 4),
(18, 'Seguridad Informática', 1, '2025-11-11 23:03:53', 1, '2025-11-11 23:03:53', 4),
(19, 'Teoría literaria', 1, '2025-11-19 16:45:38', 4, '2025-11-19 16:45:38', 1),
(20, 'Teoría literaria', 1, '2025-11-19 16:49:58', 4, '2025-11-19 16:49:58', 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `profesores`
--

CREATE TABLE `profesores` (
  `id_profesor` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `apellido` varchar(100) NOT NULL,
  `dni` varchar(20) NOT NULL,
  `email` varchar(100) NOT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `direccion` varchar(255) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL DEFAULT '',
  `rol_id` int(11) NOT NULL DEFAULT 2,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `fecha_de_alta` date NOT NULL DEFAULT curdate(),
  `numero_legajo` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `profesores`
--

INSERT INTO `profesores` (`id_profesor`, `nombre`, `apellido`, `dni`, `email`, `telefono`, `direccion`, `password_hash`, `rol_id`, `activo`, `fecha_de_alta`, `numero_legajo`) VALUES
(1, 'Lautaro', 'Aquino', '42186092', 'bianmoreyra15@hotmail.com', '03584024476', 'Calle Falsa 123, Córdoba', '$2y$10$S6Stz27AdpKzeZur2u7v9OeFKTQnHpMe3o2WfENZhLAvs/IaKgkVa', 2, 1, '2025-11-07', 'LEG001'),
(2, 'María', 'González', '28456123', 'maria.gonzalez@instituto.edu', '03704501234', 'Av. Libertad 234, Formosa', '$2y$10$abcdefghijklmnopqrstuvwxyz123456789ABCDEFGHIJ', 2, 1, '2025-11-11', 'LEG002'),
(3, 'Carlos', 'Rodríguez', '32789456', 'carlos.rodriguez@instituto.edu', '03704502345', 'Calle San Martín 567, Formosa', '$2y$10$abcdefghijklmnopqrstuvwxyz123456789ABCDEFGHIJ', 2, 1, '2025-11-11', 'LEG003'),
(4, 'Laura', 'Fernández', '35123789', 'laura.fernandez@instituto.edu', '03704503456', 'Av. 9 de Julio 890, Formosa', '$2y$10$abcdefghijklmnopqrstuvwxyz123456789ABCDEFGHIJ', 2, 1, '2025-11-11', 'LEG004'),
(5, 'Roberto', 'Martínez', '30456789', 'roberto.martinez@instituto.edu', '03704504567', 'Calle Belgrano 123, Formosa', '$2y$10$abcdefghijklmnopqrstuvwxyz123456789ABCDEFGHIJ', 2, 1, '2025-11-11', 'LEG005'),
(6, 'Ana', 'López', '33789123', 'ana.lopez@instituto.edu', '03704505678', 'Av. Kirchner 456, Formosa', '$2y$10$abcdefghijklmnopqrstuvwxyz123456789ABCDEFGHIJ', 2, 1, '2025-11-11', 'LEG006');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `roles`
--

CREATE TABLE `roles` (
  `id` int(11) NOT NULL,
  `nombre` varchar(50) NOT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `roles`
--

INSERT INTO `roles` (`id`, `nombre`, `descripcion`, `creado_en`) VALUES
(1, 'estudiante', 'Acceso para estudiantes', '2025-08-17 04:00:33'),
(2, 'profesor', 'Acceso para docentes', '2025-08-17 04:00:33'),
(3, 'secretaria', 'Acceso para personal administrativo', '2025-08-17 04:00:33'),
(4, 'director', 'Acceso para dirección', '2025-08-17 04:00:33'),
(5, 'super_usuario', 'Acceso total al sistema', '2025-08-17 04:00:33');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `secretarios`
--

CREATE TABLE `secretarios` (
  `id_secretaria` int(11) NOT NULL,
  `dni` varchar(20) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `apellido` varchar(100) NOT NULL,
  `email` varchar(120) NOT NULL,
  `telefono` varchar(30) DEFAULT NULL,
  `direccion` varchar(255) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `rol_id` int(11) NOT NULL DEFAULT 3,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `fecha_de_alta` date NOT NULL DEFAULT curdate(),
  `numero_legajo` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `secretarios`
--

INSERT INTO `secretarios` (`id_secretaria`, `dni`, `nombre`, `apellido`, `email`, `telefono`, `direccion`, `password_hash`, `rol_id`, `activo`, `fecha_de_alta`, `numero_legajo`) VALUES
(1, '10101010', 'Secretario', 'Ejemplo', 'secretario@prueba.com', '03584024476', 'Oficina Sec. 101, Córdoba (ficticio)', '$2y$10$0qSbM57w4QlZXzQ25xaZZeoAtONoERYcXfSnYomh2RlFYSpcPKp22', 3, 1, '2025-11-07', 'SEC001');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `super_usuario`
--

CREATE TABLE `super_usuario` (
  `id_super_usuario` int(11) NOT NULL,
  `dni` varchar(20) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `apellido` varchar(100) NOT NULL,
  `email` varchar(120) NOT NULL,
  `telefono` varchar(30) DEFAULT NULL,
  `direccion` varchar(255) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `rol_id` int(11) NOT NULL DEFAULT 5,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `fecha_de_alta` date NOT NULL DEFAULT curdate(),
  `numero_legajo` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `super_usuario`
--

INSERT INTO `super_usuario` (`id_super_usuario`, `dni`, `nombre`, `apellido`, `email`, `telefono`, `direccion`, `password_hash`, `rol_id`, `activo`, `fecha_de_alta`, `numero_legajo`) VALUES
(1, '00000000', 'Admin', 'Principal', 'admin@proyecto.com', '0000000000', 'Admin House 000, Córdoba (ficticio)', '$2y$10$/pMqIwUYYxBylAR2ye1rUukF0dmoX9aTyEw41gzqV8lHidGyotQca', 5, 1, '2025-11-07', 'SUP001');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `analitico`
--
ALTER TABLE `analitico`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unico_estudiante_materia` (`id_estudiante`,`id_materia`),
  ADD KEY `fk_analitico_materia` (`id_materia`);

--
-- Indices de la tabla `asignaciones`
--
ALTER TABLE `asignaciones`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `profesor_materia_anio` (`id_profesor`,`id_materia`,`anio_lectivo`),
  ADD KEY `fk_asignaciones_profesor` (`id_profesor`),
  ADD KEY `fk_asignaciones_materia` (`id_materia`);

--
-- Indices de la tabla `asistencias`
--
ALTER TABLE `asistencias`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_asistencias_usuario` (`id_usuario`),
  ADD KEY `fk_asistencias_curso` (`id_materia`);

--
-- Indices de la tabla `auditoria`
--
ALTER TABLE `auditoria`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_fecha_hora` (`fecha_hora`),
  ADD KEY `idx_usuario_tipo` (`id_usuario`,`tipo_usuario`),
  ADD KEY `idx_objeto` (`objeto_afectado`,`id_objeto`);

--
-- Indices de la tabla `carreras`
--
ALTER TABLE `carreras`
  ADD PRIMARY KEY (`id_carrera`),
  ADD KEY `idx_nombre` (`nombre`);

--
-- Indices de la tabla `correlatividades`
--
ALTER TABLE `correlatividades`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unica_correlativa` (`id_materia`,`id_materia_correlativa`),
  ADD KEY `fk_corr_correlativa` (`id_materia_correlativa`);

--
-- Indices de la tabla `cursos`
--
ALTER TABLE `cursos`
  ADD PRIMARY KEY (`id_curso`);

--
-- Indices de la tabla `directivos`
--
ALTER TABLE `directivos`
  ADD PRIMARY KEY (`id_direccion`),
  ADD UNIQUE KEY `dni` (`dni`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `numero_legajo` (`numero_legajo`),
  ADD KEY `fk_directivos_rol` (`rol_id`);

--
-- Indices de la tabla `estudiante`
--
ALTER TABLE `estudiante`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `dni` (`dni`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `fk_estudiante_rol` (`rol_id`),
  ADD KEY `fk_estudiante_carrera` (`id_carrera`);

--
-- Indices de la tabla `materias`
--
ALTER TABLE `materias`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_materias_carreras` (`id_carrera`);

--
-- Indices de la tabla `profesores`
--
ALTER TABLE `profesores`
  ADD PRIMARY KEY (`id_profesor`),
  ADD UNIQUE KEY `dni` (`dni`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `numero_legajo` (`numero_legajo`);

--
-- Indices de la tabla `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nombre` (`nombre`);

--
-- Indices de la tabla `secretarios`
--
ALTER TABLE `secretarios`
  ADD PRIMARY KEY (`id_secretaria`),
  ADD UNIQUE KEY `dni` (`dni`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `numero_legajo` (`numero_legajo`),
  ADD KEY `fk_secretarios_rol` (`rol_id`);

--
-- Indices de la tabla `super_usuario`
--
ALTER TABLE `super_usuario`
  ADD PRIMARY KEY (`id_super_usuario`),
  ADD UNIQUE KEY `dni` (`dni`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `numero_legajo` (`numero_legajo`),
  ADD KEY `fk_super_usuario_rol` (`rol_id`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `analitico`
--
ALTER TABLE `analitico`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `asignaciones`
--
ALTER TABLE `asignaciones`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `asistencias`
--
ALTER TABLE `asistencias`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'ID único de la asistencia', AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `auditoria`
--
ALTER TABLE `auditoria`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT de la tabla `carreras`
--
ALTER TABLE `carreras`
  MODIFY `id_carrera` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `correlatividades`
--
ALTER TABLE `correlatividades`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `cursos`
--
ALTER TABLE `cursos`
  MODIFY `id_curso` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `directivos`
--
ALTER TABLE `directivos`
  MODIFY `id_direccion` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `estudiante`
--
ALTER TABLE `estudiante`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT de la tabla `materias`
--
ALTER TABLE `materias`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT de la tabla `profesores`
--
ALTER TABLE `profesores`
  MODIFY `id_profesor` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de la tabla `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de la tabla `secretarios`
--
ALTER TABLE `secretarios`
  MODIFY `id_secretaria` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `super_usuario`
--
ALTER TABLE `super_usuario`
  MODIFY `id_super_usuario` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `analitico`
--
ALTER TABLE `analitico`
  ADD CONSTRAINT `fk_analitico_estudiante` FOREIGN KEY (`id_estudiante`) REFERENCES `estudiante` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_analitico_materia` FOREIGN KEY (`id_materia`) REFERENCES `materias` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `asignaciones`
--
ALTER TABLE `asignaciones`
  ADD CONSTRAINT `fk_asignaciones_materia` FOREIGN KEY (`id_materia`) REFERENCES `materias` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_asignaciones_profesor` FOREIGN KEY (`id_profesor`) REFERENCES `profesores` (`id_profesor`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `asistencias`
--
ALTER TABLE `asistencias`
  ADD CONSTRAINT `fk_asistencias_materia` FOREIGN KEY (`id_materia`) REFERENCES `materias` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_asistencias_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `estudiante` (`id`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `correlatividades`
--
ALTER TABLE `correlatividades`
  ADD CONSTRAINT `fk_corr_correlativa` FOREIGN KEY (`id_materia_correlativa`) REFERENCES `materias` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_corr_materia` FOREIGN KEY (`id_materia`) REFERENCES `materias` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `directivos`
--
ALTER TABLE `directivos`
  ADD CONSTRAINT `fk_directivos_rol` FOREIGN KEY (`rol_id`) REFERENCES `roles` (`id`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `estudiante`
--
ALTER TABLE `estudiante`
  ADD CONSTRAINT `fk_estudiante_carrera` FOREIGN KEY (`id_carrera`) REFERENCES `carreras` (`id_carrera`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_estudiante_rol` FOREIGN KEY (`rol_id`) REFERENCES `roles` (`id`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `materias`
--
ALTER TABLE `materias`
  ADD CONSTRAINT `fk_materias_carreras` FOREIGN KEY (`id_carrera`) REFERENCES `carreras` (`id_carrera`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `secretarios`
--
ALTER TABLE `secretarios`
  ADD CONSTRAINT `fk_secretarios_rol` FOREIGN KEY (`rol_id`) REFERENCES `roles` (`id`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `super_usuario`
--
ALTER TABLE `super_usuario`
  ADD CONSTRAINT `fk_super_usuario_rol` FOREIGN KEY (`rol_id`) REFERENCES `roles` (`id`) ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
