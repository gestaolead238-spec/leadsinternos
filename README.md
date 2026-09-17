# Leadsite

Painel interno para descoberta e organização de leads por cidade, bairro e nicho.

## Rodar localmente

```bash
GOOGLE_MAPS_API_KEY="sua_chave_google" SERPAPI_KEY="sua_chave_serpapi" php -S localhost:8000
```

Acesse `http://localhost:8000`. A aplicação é autocontida em `index.php`, prioriza a Google Places API quando `GOOGLE_MAPS_API_KEY` está definida e usa SerpApi como fallback. Ao escolher uma cidade, os bairros são buscados dinamicamente; o catálogo oficial completo de municípios vem do IBGE.

Não coloque a chave diretamente no código ou no HTML. No servidor, defina a variável de ambiente antes de iniciar o PHP.