# Dicionario RAG - Plugin de WordPress

Un plugin de WordPress que permite acceder ao dicionario oficial da Real Academia Galega (RAG) cunha interface moderna e responsiva.

## Aviso legal

Este plugin foi desenvolvido unicamente con fins educativos e experimentais. O seu propósito é demostrar como se pode integrar información léxica nun sitio web WordPress.

Este plugin realiza scraping da web da Real Academia Galega (https://www.academia.gal), pero esa web está protexida por dereitos de propiedade intelectual e prohíbe explicitamente a extracción, reutilización ou redistribución dos seus contidos, segundo o seu aviso legal oficial.

O uso deste plugin para acceder, extraer ou mostrar contidos da web da RAG sen autorización expresa pode violar as súas condicións de uso e/ou a lexislación vixente.

O autor deste plugin **non se responsabiliza do uso que cada persoa faga del**. Cada usuario é o único responsable de asegurarse de cumprir coa legalidade e coas condicións de uso dos sitios web cos que interactúe.

Recoméndase encarecidamente solicitar permiso á Real Academia Galega antes de empregar este plugin para acceder aos seus contidos. https://github.com/sanchezanxo/dicionario-rag/blob/main/README.md

## Descrición

Este plugin permite aos usuarios buscar definicións de palabras en galego e conxugacións verbais directamente dende o teu sitio WordPress. Conéctase á API oficial do dicionario da RAG e mostra os resultados nun formato limpo e adaptado a móbiles.

## Funcionalidades

- Busca de definicións de palabras galegas  
- Conxugacións verbais completas  
- Deseño responsivo para todos os dispositivos  
- Interface limpa e moderna  
- Fonte de datos oficial da RAG  
- Buscas mediante AJAX  
- Integración con shortcode  

## Requisitos

- WordPress 5.0 ou superior  
- PHP 7.4 ou superior  
- Conexión a internet activa  

## Instalación

1. Descarga os ficheiros do plugin  
2. Sobe o cartafol `dicionario-rag` a `/wp-content/plugins/`  
3. Activa o plugin desde o panel de administración de WordPress  
4. Usa o shortcode `[dicionario_rag]` en calquera entrada ou páxina  

## Uso

Engade o shortcode `[dicionario_rag]` en calquera entrada, páxina ou widget onde queiras que apareza o formulario de busca do dicionario.

Os usuarios poden:  
- Escribir unha palabra en galego no campo de busca  
- Premer en "Definición" para ver os significados e exemplos  
- Premer en "Conxugación" para ver a conxugación verbal completa  

## Detalles técnicos

- Conexión coa web de academia.gal  
- Peticións AJAX seguras con validación por nonce  
- Disposición responsiva mediante CSS grid  
- Entrada saneada e saída escapada  
- Xestión de erros e rexistro de logs  

## Capturas de pantalla

1. Interface principal de busca  
2. Visualización dos resultados de definición  
3. Táboas de conxugación verbal  
4. Vista responsiva en dispositivos móbiles  

## Rexistro de cambios

### 1.0.0  
- Versión inicial  
- Funcionalidade de busca de definicións  
- Función de conxugación verbal  
- Interface responsiva  
- Implementacións de seguridade  

## Licenza

Este plugin distribúese baixo a licenza GPL v2 ou posterior.

## Créditos

- Datos do dicionario fornecidos pola Real Academia Galega  
- Desenvolto por Anxo Sánchez  

## Soporte

Para problemas ou dúbidas, visita o repositorio do plugin ou contacta co desenvolvedor.
