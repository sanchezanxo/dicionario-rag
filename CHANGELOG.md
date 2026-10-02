# Rexistro de cambios

Todos os cambios relevantes no plugin Dicionario RAG quedarán documentados neste ficheiro.

O formato baséase en [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),  
e este proxecto segue as normas de [Versionado Semántico](https://semver.org/spec/v2.0.0.html).

## [1.1.0] - 2026-10-02

### Seguridade
- O HTML da conxugación que vén da RAG fíltrase con `wp_kses` antes de mostralo (sen scripts nin eventos)
- Validación da palabra: só letras, espazos, guións e apóstrofos (os verbos, unha soa palabra)
- Límite de peticións á RAG por IP (30 cada 10 minutos, só para consultas non cacheadas)
- Eliminado o nonce, que caducaba nas páxinas cacheadas (é unha consulta pública de só lectura)

### Rendemento
- Caché das consultas con transients (1 semana; 1 día para palabras non atopadas)
- O authToken gárdase na caché en vez de descargar a portada da RAG en cada conxugación
- O CSS e o JS só se cargan nas páxinas co shortcode
- Eliminada a importación de Google Fonts

### Corrixido
- As definicións agrúpanse por categoría gramatical (antes só se mostraba a primeira)
- As acepcións das frases feitas xa non aparecen mesturadas coas definicións principais
- Móstranse as remisións ("Véxase: …") en entradas e frases feitas que antes saían baleiras
- Pronomes das táboas de conxugación: o contador reiníciase por táboa, o xerundio non leva pronome e o participio mostra xénero e número
- Verbos con maiúsculas acentuadas (`PÓR`) usando `mb_strtolower`
- Premer Enter facía dúas peticións
- O botón "Definición" cambiaba a "Consultar" despois de cada busca; agora desactívanse os dous botóns durante a consulta
- Varios shortcodes na mesma páxina xa non comparten IDs
- Peticións coa API HTTP de WordPress (`wp_remote_post`) en vez de cURL: respecta o proxy do sitio, descomprime as respostas e evita `curl_close()`, obsoleta en PHP 8.5

### Cambiado
- Os estilos herdan do tema (tipografía, cores, botóns `wp-element-button` e títulos); personalizables con variables CSS
- User-Agent identificable do plugin en vez de simular un navegador
- Tempo máximo de espera reducido de 30 a 15 segundos
- Probado ata WordPress 7.1 e PHP 8.4
- Engadido `uninstall.php`, que borra os transients do plugin

## [1.0.0] - 2025-01-28

### Engadido
- Primeira versión do plugin
- Funcionalidade de busca de definicións de palabras
- Táboas completas de conxugación verbal
- Deseño de interface responsiva
- Buscas mediante peticións AJAX
- Implementación de seguridade con validación por nonce
- Saneamento de entrada e escapado de saída
- Xestión de erros e rexistro de logs
- Integración do shortcode `[dicionario_rag]`
- Disposición responsiva adaptada a móbiles
- Busca na web da Real Academia Galega
- Estrutura de plugin organizada con recursos e ficheiros
- Licenza GPL v2
- Documentación completa

### Funcionalidades técnicas
- Namespace personalizado (ASG_) para evitar conflitos
- Constantes configurables para URLs e tempos de espera
- Funcionalidade limpa de desinstalación
- Estrutura profesional do código
- Preparado para traducións (text domain)

### Funcionalidades de UI/UX
- Interface de busca moderna
- Táboas individuais para cada tempo verbal
- Mellora do deseño en grella para outras formas
- Inserción automática de pronomes
- Renomeado dos tempos verbais aos nomes oficiais en galego
- Estados de carga e mensaxes de retroalimentación ao usuario
