# SEO & Legibilidade — plugin para WordPress

Plugin que analisa e **corrige automaticamente** o SEO e a legibilidade de posts, páginas e tipos de conteúdo personalizados, além de gerar as meta tags, os dados estruturados e o sitemap.

- Análise em tempo real no editor (Gutenberg e editor clássico), com nota de 0 a 100 para SEO e para legibilidade.
- Botão **"Corrigir automaticamente"**: gera título SEO, meta descrição e slug otimizados e preenche o texto alternativo das imagens.
- Prévia de como o resultado aparece no Google.
- Análise em **português** (Flesch adaptado por Martins et al., voz passiva, conectivos) e em **inglês**.

**Versão web:** https://joaogabrielmontinirossi-sys.github.io/Seo/ faz a mesma análise direto no navegador (sem servidor: o texto não sai do seu computador) e oferece o plugin para download.

## Instalação

1. Copie esta pasta para `wp-content/plugins/seo-legibilidade/` (ou compacte em `.zip` e envie em **Plugins → Adicionar novo → Enviar plugin**).
2. Ative o plugin.
3. Vá em **Configurações → SEO & Legibilidade** e preencha o nome e o logotipo da organização, os perfis sociais, a imagem social padrão e os códigos de verificação do Google e do Bing.
4. Envie `https://seusite.com/wp-sitemap.xml` ao Google Search Console.

Requisitos: WordPress 6.2+ e PHP 7.4+. Use links permanentes com **"Nome do post"**. O tema precisa declarar `add_theme_support( 'title-tag' )`; quase todos os temas atuais declaram.

> Se você já usa Yoast, Rank Math ou All in One SEO, desative-o antes. Dois plugins de SEO ao mesmo tempo duplicam as meta tags.

## O que é verificado

### SEO (on-page)

| Verificação | Padrão adotado |
|---|---|
| Frase-chave de foco | Precisa estar definida; até 4 palavras de conteúdo |
| Frase-chave no título SEO | Presente e, de preferência, no início |
| Largura do título | 200 a 580 px (cerca de 30 a 60 caracteres), medidos na fonte do Google (Arial 20px) |
| Meta descrição | 120 a 156 caracteres e com a frase-chave (1 ou 2 vezes) |
| Frase-chave na URL | Todas as palavras importantes no slug |
| Qualidade da URL | Até 75 caracteres, sem acentos, sem palavras vazias, sem sufixo `-2` |
| Frase-chave na introdução | No primeiro parágrafo, de preferência na primeira frase |
| Densidade da frase-chave | 0,5% a 3% do texto |
| Frase-chave nos subtítulos | Em parte dos H2/H3 (nem em nenhum, nem em mais de 75%) |
| Frase-chave já usada | Avisa sobre canibalização com outros conteúdos |
| Tamanho do texto | Mínimo de 300 palavras (configurável) |
| Imagens | Todas com texto alternativo; ao menos uma com a frase-chave |
| Links | Ao menos um link interno e um externo |
| Texto dos links | Sem "clique aqui", "saiba mais" ou âncoras vazias |
| Hierarquia de títulos | Sem H1 no conteúdo (o título do post já é o H1) e sem pular níveis |

### Legibilidade

| Verificação | Padrão adotado |
|---|---|
| Facilidade de leitura (Flesch) | 60 pontos ou mais. Português: `248,835 − 1,015 × palavras/frase − 84,6 × sílabas/palavra` (Martins et al., 1996) |
| Comprimento das frases | No máximo 25% das frases com mais de 20 palavras |
| Comprimento dos parágrafos | Até 150 palavras (alerta acima de 120) |
| Distribuição de subtítulos | Até 300 palavras entre subtítulos |
| Voz passiva | No máximo 10% das frases |
| Palavras de transição | Ao menos 30% das frases (textos com 200 palavras ou mais) |
| Início de frases | Nunca 3 ou mais frases seguidas começando com a mesma palavra |
| Palavras complexas | No máximo 10% de palavras longas |
| Listas | Recomendadas em textos com mais de 600 palavras |

A nota de cada aba vai de 0 a 100: verde a partir de 71, laranja de 41 a 70 e vermelho até 40.

## O que é gerado no site

- **`<title>`** por modelos com variáveis (`%%title%%`, `%%sitename%%`, `%%sep%%`, `%%category%%`, `%%page%%`, `%%currentyear%%`, `%%term%%`).
- **Meta descrição**: a personalizada, ou o resumo, ou uma gerada a partir do texto (até 156 caracteres, partindo da frase que contém a frase-chave).
- **Canonical** em posts, páginas, página inicial, categorias, tags, autores e páginas paginadas (cada página paginada aponta para si mesma, como o Google recomenda). Substitui o canonical nativo.
- **Robots**: `max-image-preview:large, max-snippet:-1, max-video-preview:-1` em páginas indexáveis. `noindex, follow` em buscas internas, páginas 404, arquivos por data (opcional), arquivos de autor (opcional), conteúdo protegido por senha e itens marcados como noindex.
- **Open Graph** (Facebook, LinkedIn, WhatsApp) e **Twitter/X Cards**, com dimensões e texto alternativo da imagem.
- **JSON-LD** em um único `@graph` conectado: `Organization` ou `Person` (com logotipo e `sameAs`), `WebSite`, `WebPage`, `CollectionPage` ou `SearchResultsPage`, `BlogPosting`, `Article` ou `NewsArticle` (com autor, datas, imagem, palavras-chave, seção e contagem de palavras) e `BreadcrumbList`.
- **Sitemap XML** nativo do WordPress (`/wp-sitemap.xml`) melhorado: inclui `<lastmod>`, exclui conteúdo noindex e conteúdo com canonical apontando para outra URL, e remove os formatos de post e (opcionalmente) os autores.
- **Verificação** do Google Search Console e do Bing Webmaster Tools.
- **Correções automáticas na exibição**: as páginas de anexo são redirecionadas (301) para o post ou para o arquivo, e imagens sem `alt` recebem o alt da biblioteca de mídia, a legenda, o título ou o nome do arquivo.

## Versão web (GitHub Pages)

A pasta `web/` é um site estático com o mesmo motor de análise portado para JavaScript (`web/assets/analyzer.js`). A cada push no branch padrão, o workflow `.github/workflows/pages.yml` roda os testes, monta o site com `tools/build-site.sh` (incluindo `seo-legibilidade.zip`) e publica no GitHub Pages.

Para ativar uma única vez: **Settings → Pages → Build and deployment → Source: GitHub Actions**.

Para manter os dois motores iguais:

- as listas de palavras ficam só no PHP; `php tools/build-language-data.php` gera `web/assets/language-data.js`;
- `node tests/parity.js` roda os mesmos casos nos dois motores e exige resultados idênticos.

Para ver localmente: `tools/build-site.sh && python3 -m http.server -d _site`.

## Desenvolvimento

```bash
php tests/run.php      # testes dos analisadores e das correções automáticas, sem dependências
node tests/parity.js   # paridade entre o motor PHP e o JavaScript
```

Estrutura:

```
seo-legibilidade.php            Inicialização
includes/analysis/              Motor de análise (sem dependência do WordPress)
  class-text.php                Frases, palavras, sílabas, largura em pixels, frase-chave
  class-language.php            Conectivos, voz passiva e palavras vazias (pt/en), Flesch
  class-seo-analyzer.php        Verificações de SEO
  class-readability-analyzer.php Verificações de legibilidade
includes/class-autofix.php      Gera título, descrição, slug e alt dentro dos padrões
includes/class-frontend.php     <title>, meta tags, robots, canonical, OG/Twitter
includes/class-schema.php       JSON-LD
includes/class-sitemap.php      Ajustes do sitemap nativo
includes/class-rest.php         Endpoints /seo-legibilidade/v1/analyze e /fix
includes/class-admin.php        Painel do editor, colunas, termos, configurações
assets/                         JS e CSS do painel (sem etapa de build)
web/                            Versão web estática (GitHub Pages)
tools/                          Geração de language-data.js e do site
```

Filtro disponível: `seol_schema_graph` (array) para alterar o grafo JSON-LD.

## Fontes dos critérios

- Google Search Central: [links de título](https://developers.google.com/search/docs/appearance/title-link), [snippets e meta descrição](https://developers.google.com/search/docs/appearance/snippet), [estrutura de URL](https://developers.google.com/search/docs/crawling-indexing/url-structure), [canonical](https://developers.google.com/search/docs/crawling-indexing/consolidate-duplicate-urls), [paginação](https://developers.google.com/search/docs/specialty/ecommerce/pagination-and-incremental-page-loading), [robots meta tag](https://developers.google.com/search/docs/crawling-indexing/robots-meta-tag), [imagens](https://developers.google.com/search/docs/appearance/google-images), [links](https://developers.google.com/search/docs/crawling-indexing/links-crawlable), [Article](https://developers.google.com/search/docs/appearance/structured-data/article), [Breadcrumb](https://developers.google.com/search/docs/appearance/structured-data/breadcrumb) e [Logo](https://developers.google.com/search/docs/appearance/structured-data/logo).
- Limiares de legibilidade e de frase-chave: documentação de pontuação do [YoastSEO.js](https://www.npmjs.com/package/yoastseo).
- Flesch para português: Martins et al. (1996), conforme o [NILC-Metrix](https://arxiv.org/abs/2201.03445) e o [ALT](https://arxiv.org/abs/2203.12135).
- Acessibilidade de imagens e links: WCAG 2.2 (1.1.1 Conteúdo não textual e 2.4.4 Finalidade do link).
- Open Graph: [ogp.me](https://ogp.me/). Twitter/X Cards: [documentação do X](https://developer.x.com/en/docs/x-for-websites/cards/overview/markup).

## Licença

GPL-2.0-or-later.
