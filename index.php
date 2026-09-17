<?php
session_start();

$envFile = __DIR__ . '/.env';
if (is_readable($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $envLine) {
        if (str_starts_with(trim($envLine), '#') || !str_contains($envLine, '=')) continue;
        [$envName, $envValue] = explode('=', $envLine, 2);
        if (getenv(trim($envName)) === false) putenv(trim($envName) . '=' . trim($envValue, " \t\"") );
    }
}

$leads = [
    ['id'=>1,'name'=>'Studio Movimento Pilates','category'=>'Pilates','city'=>'São Paulo','state'=>'SP','neighborhood'=>'Vila Madalena','phone'=>'(11) 3814-2040','rating'=>'4,9','reviews'=>184,'website'=>'studiomovimento.com.br','lastSeen'=>'Hoje, 09:42','color'=>'#f5b544'],
    ['id'=>2,'name'=>'Clínica Vitta Odontologia','category'=>'Clínica odontológica','city'=>'São Paulo','state'=>'SP','neighborhood'=>'Moema','phone'=>'(11) 5096-1182','rating'=>'4,8','reviews'=>96,'website'=>'clinicavitta.com.br','lastSeen'=>'Hoje, 09:18','color'=>'#5b8def'],
    ['id'=>3,'name'=>'Barbearia Estação 12','category'=>'Barbearia','city'=>'Campinas','state'=>'SP','neighborhood'=>'Cambuí','phone'=>'(19) 3234-7721','rating'=>'4,7','reviews'=>61,'website'=>'barbeariaestacao.com.br','lastSeen'=>'Hoje, 08:57','color'=>'#ef886f'],
    ['id'=>4,'name'=>'Espaço Corpo & Equilíbrio','category'=>'Fisioterapia','city'=>'Curitiba','state'=>'PR','neighborhood'=>'Batel','phone'=>'(41) 3029-8870','rating'=>'4,9','reviews'=>132,'website'=>'corpoequilibrio.com.br','lastSeen'=>'Ontem, 17:44','color'=>'#6dbb9a'],
    ['id'=>5,'name'=>'PetCare Veterinária','category'=>'Veterinário','city'=>'Belo Horizonte','state'=>'MG','neighborhood'=>'Savassi','phone'=>'(31) 3281-4490','rating'=>'4,6','reviews'=>73,'website'=>'petcarebh.com.br','lastSeen'=>'Ontem, 16:20','color'=>'#b487e7'],
    ['id'=>6,'name'=>'Instituto Derma Saúde','category'=>'Dermatologista','city'=>'Rio de Janeiro','state'=>'RJ','neighborhood'=>'Botafogo','phone'=>'(21) 2541-0628','rating'=>'4,8','reviews'=>205,'website'=>'institutoderma.com.br','lastSeen'=>'Ontem, 15:11','color'=>'#ec6e9d'],
    ['id'=>7,'name'=>'Studio Alma Yoga','category'=>'Yoga','city'=>'Florianópolis','state'=>'SC','neighborhood'=>'Lagoa da Conceição','phone'=>'(48) 3232-9010','rating'=>'4,9','reviews'=>88,'website'=>'studioalmayoga.com.br','lastSeen'=>'Ontem, 14:35','color'=>'#52b6ad'],
    ['id'=>8,'name'=>'Núcleo Psicologia Viva','category'=>'Psicólogo','city'=>'São Paulo','state'=>'SP','neighborhood'=>'Pinheiros','phone'=>'(11) 3031-5568','rating'=>'5,0','reviews'=>47,'website'=>'psicologiaviva.com.br','lastSeen'=>'Ontem, 13:06','color'=>'#d79c5b'],
];
if (!isset($_SESSION['crm'])) $_SESSION['crm'] = [];
if (!isset($_SESSION['contacted'])) $_SESSION['contacted'] = [];
if (!isset($_SESSION['catalog'])) $_SESSION['catalog'] = $leads;

function searchSerpApiPage($term, $apiKey, $start = 0) {
    if ($term === '' || $apiKey === '' || !function_exists('curl_init')) return [];
    $url = 'https://serpapi.com/search.json?' . http_build_query([
        'engine' => 'google_maps', 'q' => $term, 'hl' => 'pt-br', 'gl' => 'br',
        'type' => 'search', 'start' => $start, 'api_key' => $apiKey,
    ]);
    $curl = curl_init($url);
    curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15, CURLOPT_FOLLOWLOCATION => true]);
    $body = curl_exec($curl);
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
    if ($body === false || $httpCode >= 400) return [];
    $payload = json_decode($body, true);
    $results = [];
    foreach ($payload['local_results'] ?? [] as $index => $item) {
        $name = trim($item['title'] ?? '');
        if ($name === '') continue;
        $address = $item['address'] ?? '';
        $results[] = [
            'id' => abs(crc32(($item['place_id'] ?? $item['data_id'] ?? '') . $name)) + 100,
            'name' => $name,
            'category' => $item['type'] ?? 'Negócio local',
            'city' => '', 'state' => '', 'neighborhood' => $address,
            'phone' => $item['phone'] ?? 'Telefone não informado',
            'rating' => str_replace('.', ',', (string)($item['rating'] ?? '-')),
            'reviews' => $item['reviews'] ?? 0,
            'website' => $item['website'] ?? '',
            'cnpj' => $item['cnpj'] ?? '',
            'whatsapp' => $item['whatsapp'] ?? '',
            'lastSeen' => 'Agora, via Google Maps',
            'color' => ['#5b8def', '#6dbb9a', '#ef886f', '#b487e7'][$index % 4],
        ];
    }
    return $results;
}

function searchSerpApi($term, $apiKey, $maxResults = 100) {
    $results = [];
    $known = [];
    for ($start = 0; count($results) < $maxResults; $start += 20) {
        $page = searchSerpApiPage($term, $apiKey, $start);
        if (!$page) break;
        foreach ($page as $result) {
            $key = $result['place_id'] ?? strtolower($result['name'] . '|' . $result['address']);
            if (isset($known[$key])) continue;
            $known[$key] = true;
            $results[] = $result;
            if (count($results) >= $maxResults) break;
        }
        if (count($page) < 20) break;
    }
    return $results;
}

function searchGooglePlaces($term, $apiKey) {
    if ($term === '' || $apiKey === '' || !function_exists('curl_init')) return [];
    $url = 'https://maps.googleapis.com/maps/api/place/textsearch/json?' . http_build_query([
        'query' => $term, 'language' => 'pt-BR', 'region' => 'br', 'key' => $apiKey,
    ]);
    $curl = curl_init($url);
    curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15, CURLOPT_FOLLOWLOCATION => true]);
    $body = curl_exec($curl);
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
    if ($body === false || $httpCode >= 400) return [];
    $payload = json_decode($body, true);
    return $payload['results'] ?? [];
}

function googlePlacesToLeads($places) {
    $leads = [];
    foreach ($places as $index => $place) {
        $name = trim($place['name'] ?? '');
        if ($name === '') continue;
        $leads[] = [
            'id' => abs(crc32(($place['place_id'] ?? '') . $name)) + 100,
            'name' => $name,
            'category' => $place['types'][0] ?? 'Negócio local',
            'city' => '', 'state' => '', 'neighborhood' => $place['formatted_address'] ?? '',
            'phone' => '', 'rating' => str_replace('.', ',', (string)($place['rating'] ?? '-')),
            'reviews' => $place['user_ratings_total'] ?? 0, 'website' => '', 'cnpj' => '', 'whatsapp' => '',
            'lastSeen' => 'Agora, via Google Places', 'color' => ['#5b8def', '#6dbb9a', '#ef886f', '#b487e7'][$index % 4],
        ];
    }
    return $leads;
}

function neighborhoodsForCity($city, $state, $googleKey, $serpKey) {
    if ($city === '') return [];
    $term = 'comércios em ' . $city . ($state !== '' ? ', ' . $state : '');
    $addresses = [];
    $googleResults = searchGooglePlaces($term, $googleKey);
    if ($googleResults) {
        foreach ($googleResults as $result) if (!empty($result['formatted_address'])) $addresses[] = $result['formatted_address'];
    } else {
        foreach (searchSerpApi($term, $serpKey) as $result) if (!empty($result['neighborhood'])) $addresses[] = $result['neighborhood'];
    }
    $neighborhoods = [];
    foreach ($addresses as $address) {
        $parts = array_values(array_filter(array_map('trim', explode(',', $address))));
        if (count($parts) < 2) continue;
        $candidate = $parts[count($parts) - 2];
        if (stripos($candidate, $city) === false && !preg_match('/\b[A-Z]{2}\b/', $candidate)) $neighborhoods[] = $candidate;
    }
    $neighborhoods = array_values(array_unique($neighborhoods));
    natcasesort($neighborhoods);
    return array_values($neighborhoods);
}

if (($_GET['ajax'] ?? '') === 'neighborhoods') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(neighborhoodsForCity(trim($_GET['city'] ?? ''), trim($_GET['state'] ?? ''), getenv('GOOGLE_MAPS_API_KEY') ?: '', getenv('SERPAPI_KEY') ?: ''), JSON_UNESCAPED_UNICODE);
    exit;
}

$action = $_POST['action'] ?? '';
$selected = array_map('intval', $_POST['selected'] ?? []);
if ($action === 'add_to_crm') {
    foreach ($selected as $id) if (!in_array($id, $_SESSION['crm'], true)) $_SESSION['crm'][] = $id;
    $_SESSION['flash'] = count($selected) . ' lead' . (count($selected) === 1 ? '' : 's') . ' enviado' . (count($selected) === 1 ? '' : 's') . ' para o CRM.';
    header('Location: ' . $_SERVER['PHP_SELF'] . '?view=crm'); exit;
}
if ($action === 'remove_from_crm') {
    $_SESSION['crm'] = array_values(array_diff($_SESSION['crm'], $selected));
    $_SESSION['flash'] = 'Lead removido do CRM.';
    header('Location: ' . $_SERVER['PHP_SELF'] . '?view=crm'); exit;
}
if ($action === 'mark_contacted') {
    foreach ($selected as $id) $_SESSION['contacted'][] = $id;
    $_SESSION['contacted'] = array_values(array_unique($_SESSION['contacted']));
    $_SESSION['flash'] = 'Contato atualizado.';
    header('Location: ' . $_SERVER['PHP_SELF'] . '?view=crm'); exit;
}

$view = $_GET['view'] ?? 'leads';
$query = trim($_GET['q'] ?? '');
$filters = ['state'=>$_GET['state'] ?? '', 'city'=>$_GET['city'] ?? '', 'neighborhood'=>$_GET['neighborhood'] ?? '', 'category'=>$_GET['category'] ?? ''];
$googleKey = getenv('GOOGLE_MAPS_API_KEY') ?: '';
$apiKey = getenv('SERPAPI_KEY') ?: '';
$searchTerms = array_filter([$filters['category'], $filters['neighborhood'], $filters['city'], $filters['state'], $query]);
$apiResults = [];
if ($view === 'leads' && $searchTerms) {
    $apiResults = $googleKey !== '' ? googlePlacesToLeads(searchGooglePlaces(implode(', ', $searchTerms), $googleKey)) : searchSerpApi(implode(', ', $searchTerms), $apiKey);
}
$apiWarning = $view === 'leads' && $searchTerms && !$apiResults && $googleKey === '' && $apiKey === '' ? 'Configure GOOGLE_MAPS_API_KEY ou SERPAPI_KEY para buscar empresas reais.' : '';
if ($apiResults) {
    foreach ($apiResults as $apiLead) {
        $_SESSION['catalog'] = array_values(array_filter($_SESSION['catalog'], fn($lead) => $lead['id'] !== $apiLead['id']));
        $_SESSION['catalog'][] = $apiLead;
    }
}
$catalog = $_SESSION['catalog'];
$crmLeads = array_values(array_filter($catalog, fn($lead) => in_array($lead['id'], $_SESSION['crm'], true)));
$shown = $view === 'crm' ? $crmLeads : array_values(array_filter($apiResults ?: $catalog, function ($lead) use ($filters, $query, $apiResults) {
    if ($apiResults) return true;
    foreach ($filters as $key => $value) if ($value !== '' && stripos($lead[$key], $value) === false) return false;
    if ($query !== '' && stripos(implode(' ', $lead), $query) === false) return false;
    return true;
}));
$flash = $_SESSION['flash'] ?? ''; unset($_SESSION['flash']);
if ($apiWarning) $flash = $apiWarning;
$countContacted = count(array_intersect($_SESSION['crm'], $_SESSION['contacted']));
$states = ['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO'];
$cities = array_unique(array_column($leads, 'city')); sort($cities);
$citiesByState = [];
foreach ($leads as $lead) $citiesByState[$lead['state']][] = $lead['city'];
foreach ($citiesByState as $state => $stateCities) $citiesByState[$state] = array_values(array_unique($stateCities));
$neighborhoods = array_unique(array_column($leads, 'neighborhood')); sort($neighborhoods);
$categories = [
    'Academia', 'Acupuntura', 'Agência de marketing', 'Agência de publicidade', 'Barbearia',
    'Clínica de estética', 'Clínica de fisioterapia', 'Clínica de pilates', 'Clínica de psicologia',
    'Clínica médica', 'Clínica odontológica', 'Clínica veterinária', 'Consultório médico',
    'Consultório odontológico', 'Contabilidade', 'CREF', 'Crossfit', 'Depilação', 'Dermatologista',
    'Escola de idiomas', 'Escola infantil', 'Escritório de advocacia', 'Estúdio de tatuagem',
    'Fisioterapia', 'Fotógrafo', 'Imobiliária', 'Instrutor de yoga', 'Manicure e pedicure',
    'Massoterapia', 'Nutricionista', 'Personal trainer', 'Pet shop', 'Pilates', 'Psicólogo',
    'Quiropraxia', 'Salão de beleza', 'Serviços automotivos', 'Studio de dança', 'Terapia ocupacional',
    'Veterinário', 'Yoga',
];
sort($categories);
function e($value) { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function initials($name) { $parts = preg_split('/\s+/', trim($name)); return strtoupper(substr($parts[0],0,1) . substr($parts[count($parts)-1],0,1)); }
?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Leadsite | Central de prospecção</title>
<style>
:root{--ink:#15251f;--muted:#73817b;--line:#e7ece8;--paper:#fff;--bg:#f5f8f5;--green:#18794e;--light:#e8f5ed;--shadow:0 12px 32px rgba(26,58,43,.06)}*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--ink);font:14px Arial,sans-serif}.app{display:flex;min-height:100vh}.sidebar{width:246px;background:#132c24;color:#d6e5db;padding:26px 17px;display:flex;flex-direction:column}.brand{font:bold 20px Arial;color:#fff;letter-spacing:-.7px;margin:0 12px 42px}.brand span{color:#78d29c}.nav-label{text-transform:uppercase;color:#79958a;font-size:10px;font-weight:700;letter-spacing:1.3px;margin:0 12px 10px}.nav a{display:flex;align-items:center;gap:12px;color:#a9c0b5;text-decoration:none;padding:12px;margin-bottom:4px;border-radius:8px;font-weight:500}.nav a.active,.nav a:hover{color:#fff;background:#24473a}.nav svg{width:18px;height:18px;stroke:currentColor;fill:none;stroke-width:1.8}.sidebar-bottom{margin-top:auto;padding:18px 12px 4px;border-top:1px solid #2b493d}.user{display:flex;align-items:center;gap:10px}.avatar{width:32px;height:32px;border-radius:50%;display:grid;place-items:center;background:#d8a873;color:#fff;font-size:11px;font-weight:700}.user strong{display:block;color:#fff;font-size:12px}.user small{color:#7f9d90}.main{flex:1;min-width:0}.topbar{height:76px;background:var(--paper);border-bottom:1px solid var(--line);padding:0 38px;display:flex;align-items:center;justify-content:space-between}.crumb{color:var(--muted);font-size:13px}.crumb b{color:var(--ink);font-weight:600}.top-actions{display:flex;align-items:center;gap:22px}.help{color:var(--muted);font-size:13px;text-decoration:none}.bell{position:relative;color:#60716a}.bell:after{content:'';position:absolute;right:-2px;top:0;width:5px;height:5px;border-radius:50%;background:#eb725d}.content{padding:34px 38px 55px;max-width:1500px}.heading{display:flex;justify-content:space-between;align-items:flex-end;margin-bottom:27px}.heading h1{font:bold 28px Arial;margin:0 0 7px;letter-spacing:-1px}.heading p{color:var(--muted);margin:0}.primary{background:var(--green);color:#fff;padding:11px 17px;border-radius:7px;font-weight:700;display:inline-flex;align-items:center;gap:8px}.primary:hover{background:#11683f}.filters{background:#fff;border:1px solid var(--line);border-radius:10px;padding:18px 20px;display:flex;gap:12px;align-items:flex-end;box-shadow:var(--shadow);margin-bottom:24px}.field{display:flex;flex-direction:column;gap:7px;min-width:145px;flex:1}.field label{font-size:11px;color:var(--muted);font-weight:700}.field select,.search{height:40px;border:1px solid #dce5df;background:#fbfcfb;border-radius:6px;padding:0 11px;color:var(--ink);outline:0}.field select:focus,.search:focus{border-color:#7fba9a}.search-wrap{position:relative;flex:1.45}.search-wrap svg{position:absolute;left:11px;top:12px;color:#91a19a}.search{width:100%;padding-left:35px}.filter-btn{height:40px;border:1px solid #c5d5ca;background:#fff;color:var(--green);border-radius:6px;padding:0 14px;font-weight:700}.filter-btn:hover{background:var(--light)}.summary{display:flex;align-items:center;justify-content:space-between;margin-bottom:13px}.summary strong{font-size:14px}.summary span{font-size:12px;color:var(--muted)}.cards{display:grid;grid-template-columns:repeat(auto-fill,minmax(310px,1fr));gap:13px}.card{background:#fff;border:1px solid var(--line);border-radius:10px;padding:17px;position:relative;transition:.2s box-shadow,.2s border}.card:hover{box-shadow:var(--shadow);border-color:#c8ddd0}.card-top{display:flex;align-items:flex-start;gap:11px}.logo{width:39px;height:39px;border-radius:9px;color:#fff;display:grid;place-items:center;font-weight:800;font-size:12px;flex:none}.check{position:absolute;right:16px;top:17px;width:19px;height:19px;accent-color:var(--green)}.card h3{font:bold 14px Arial;margin:1px 0 5px}.category{color:var(--muted);font-size:12px}.meta{display:flex;gap:8px;align-items:center;margin:17px 0 13px;color:#56665e;font-size:12px}.star{color:#e7a426;font-size:15px}.details{border-top:1px solid #eef2ef;padding-top:12px;color:#607069;font-size:12px;line-height:1.7}.details div{display:flex;gap:8px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.details svg{width:15px;height:15px;stroke:#9aaa9f;fill:none;stroke-width:1.7;flex:none;margin-top:2px}.card-footer{display:flex;justify-content:space-between;align-items:center;margin-top:15px}.seen{color:#a0aca6;font-size:10px}.card-action{color:var(--green);font-size:12px;font-weight:700}.flash{background:#e7f5eb;border:1px solid #c7e7d2;color:#246e46;border-radius:7px;padding:11px 14px;margin-bottom:18px;font-weight:600}.empty{background:#fff;border:1px dashed #cfdcd3;border-radius:10px;text-align:center;padding:60px;color:var(--muted)}.empty strong{display:block;color:var(--ink);font-size:16px;margin-bottom:5px}.crm-head{display:flex;gap:12px;margin-bottom:24px}.stat{background:#fff;border:1px solid var(--line);border-radius:9px;padding:15px 20px;min-width:155px}.stat b{display:block;font:bold 23px Arial}.stat span{color:var(--muted);font-size:11px}.bulk{position:sticky;bottom:20px;margin:20px auto 0;background:#173a2c;color:#fff;border-radius:9px;padding:11px 15px;display:none;align-items:center;gap:14px;width:max-content;box-shadow:0 8px 24px #15372b55}.bulk.show{display:flex}.bulk button{background:#8fe0ad;color:#123626;border-radius:5px;padding:8px 12px;font-weight:700}.bulk button.secondary{background:transparent;border:1px solid #628675;color:#dff4e6}
@media(max-width:850px){.sidebar{width:64px;padding:22px 9px}.brand{font-size:0;margin:0 0 35px;text-align:center}.brand span{font-size:22px}.nav-label,.nav a span,.user div{display:none}.nav a{justify-content:center;padding:12px}.sidebar-bottom{padding:16px 0}.user{justify-content:center}.topbar{padding:0 20px}.content{padding:26px 20px}.help{display:none}.heading{align-items:flex-start;gap:15px}.heading h1{font-size:23px}.filters{flex-wrap:wrap}.field{min-width:calc(50% - 8px)}.search-wrap{min-width:100%}.cards{grid-template-columns:repeat(auto-fill,minmax(270px,1fr))}}@media(max-width:520px){.topbar{height:64px}.content{padding:22px 14px}.heading{display:block}.heading .primary{margin-top:17px}.filters{padding:14px}.field{min-width:100%}.summary{align-items:flex-start;gap:8px}.crm-head{overflow:auto}.stat{min-width:130px}.card{padding:15px}}
</style>
</head>
<body>
<div class="app"><aside class="sidebar"><div class="brand">leads<span>site</span></div><div class="nav-label">Workspace</div><nav class="nav"><a href="?view=leads" class="<?= $view === 'leads' ? 'active' : '' ?>"><svg viewBox="0 0 24 24"><rect x="3" y="4" width="7" height="7" rx="1"/><rect x="14" y="4" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg><span>Encontrar leads</span></a><a href="?view=crm" class="<?= $view === 'crm' ? 'active' : '' ?>"><svg viewBox="0 0 24 24"><path d="M16 20v-1.8a3.2 3.2 0 0 0-3.2-3.2H6.2A3.2 3.2 0 0 0 3 18.2V20"/><circle cx="9.5" cy="7" r="3.5"/><path d="M16 11a3.5 3.5 0 0 0 0-7M17.5 15h.3a3.2 3.2 0 0 1 3.2 3.2V20"/></svg><span>Meu CRM <b style="color:#a4e0b8;font-size:11px">(<?= count($crmLeads) ?>)</b></span></a></nav><div class="sidebar-bottom"><div class="user"><div class="avatar">NO</div><div><strong>Nicolas Oliveira</strong><small>Equipe comercial</small></div></div></div></aside>
<main class="main"><header class="topbar"><div class="crumb">Workspace <span>/</span> <b><?= $view === 'crm' ? 'Meu CRM' : 'Encontrar leads' ?></b></div><div class="top-actions"><a class="help" href="#">Central de ajuda</a><span class="bell">●</span></div></header><section class="content">
<?php if ($flash): ?><div class="flash"><?= e($flash) ?></div><?php endif; ?><div class="heading"><div><h1><?= $view === 'crm' ? 'Meu CRM' : 'Encontre novos negócios' ?></h1><p><?= $view === 'crm' ? 'Acompanhe os leads que sua equipe separou para contato.' : 'Encontre empresas no Google Maps e transforme oportunidades em clientes.' ?></p></div><?php if ($view === 'leads'): ?><button class="primary" type="button" onclick="document.querySelector('.filters').scrollIntoView({behavior:'smooth'})"><span>+</span> Nova busca</button><?php endif; ?></div>
<?php if ($view === 'crm'): ?><div class="crm-head"><div class="stat"><b><?= count($crmLeads) ?></b><span>Leads salvos</span></div><div class="stat"><b><?= $countContacted ?></b><span>Contatos feitos</span></div><div class="stat"><b><?= count($crmLeads) - $countContacted ?></b><span>Aguardando contato</span></div></div><?php else: ?><form class="filters" method="get"><input type="hidden" name="view" value="leads"><div class="search-wrap"><input class="search" name="q" value="<?= e($query) ?>" placeholder="Buscar por nome ou palavra-chave"></div><div class="field"><label>Estado</label><select name="state"><option value="">Todos os estados</option><?php foreach ($states as $option): ?><option <?= $filters['state']===$option?'selected':'' ?>><?= e($option) ?></option><?php endforeach; ?></select></div><div class="field"><label>Cidade</label><select name="city"><option value="">Todas as cidades</option><?php foreach ($cities as $option): ?><option <?= $filters['city']===$option?'selected':'' ?>><?= e($option) ?></option><?php endforeach; ?></select></div><div class="field"><label>Bairro</label><select name="neighborhood"><option value="">Todos os bairros</option><?php foreach ($neighborhoods as $option): ?><option <?= $filters['neighborhood']===$option?'selected':'' ?>><?= e($option) ?></option><?php endforeach; ?></select></div><div class="field"><label>Nicho</label><select name="category"><option value="">Todos os nichos</option><?php foreach ($categories as $option): ?><option <?= $filters['category']===$option?'selected':'' ?>><?= e($option) ?></option><?php endforeach; ?></select></div><button class="filter-btn">Filtrar</button></form><?php endif; ?>
<div class="summary"><strong><?= count($shown) ?> <?= $view === 'crm' ? 'leads no seu CRM' : 'negócios encontrados' ?></strong><span><?= $view === 'crm' ? 'Organize seus próximos contatos' : 'Dados atualizados recentemente' ?></span></div>
<?php if (!$shown): ?><div class="empty"><strong><?= $view === 'crm' ? 'Seu CRM ainda está vazio' : 'Nenhum negócio encontrado' ?></strong><?= $view === 'crm' ? 'Selecione leads na busca para começar sua prospecção.' : 'Tente ajustar os filtros ou buscar por outro termo.' ?></div><?php else: ?><form method="post" id="leadsForm"><div class="cards"><?php foreach ($shown as $lead): $inCrm = in_array($lead['id'], $_SESSION['crm'], true); $contacted = in_array($lead['id'], $_SESSION['contacted'], true); ?><article class="card"><input class="check" type="checkbox" name="selected[]" value="<?= $lead['id'] ?>" aria-label="Selecionar <?= e($lead['name']) ?>"><div class="card-top"><div class="logo" style="background:<?= e($lead['color']) ?>"><?= e(initials($lead['name'])) ?></div><div><h3><?= e($lead['name']) ?></h3><div class="category"><?= e($lead['category']) ?> · <?= e($lead['neighborhood']) ?></div></div></div><div class="meta"><span class="star">★</span><b><?= e($lead['rating']) ?></b><span>(<?= e($lead['reviews']) ?> avaliações)</span></div><div class="details"><div><?= e($lead['neighborhood'] ?: trim($lead['city'] . ' - ' . $lead['state'])) ?></div><div>Telefone: <?= e($lead['phone'] ?: 'Não informado') ?></div><div>Site: <?= !empty($lead['website']) ? 'disponível' : 'não informado' ?></div><div>CNPJ: <?= e($lead['cnpj'] ?? 'Não localizado') ?></div><div>WhatsApp: <?= e($lead['whatsapp'] ?? 'Não confirmado') ?></div></div><div class="card-footer"><span class="seen"><?= e($lead['lastSeen']) ?></span><?php if ($view === 'crm'): ?><span class="card-action"><?= $contacted ? 'Contato feito' : 'Aguardando contato' ?></span><?php else: ?><span class="card-action"><?= $inCrm ? 'Já está no CRM' : 'Selecionar lead' ?></span><?php endif; ?></div></article><?php endforeach; ?></div><div class="bulk" id="bulk"><span><b id="selectedCount">0</b> selecionados</span><?php if ($view === 'crm'): ?><button type="submit" name="action" value="mark_contacted">Marcar como contatado</button><button class="secondary" type="submit" name="action" value="remove_from_crm">Remover do CRM</button><?php else: ?><button type="submit" name="action" value="add_to_crm">Adicionar ao CRM</button><?php endif; ?></div></form><?php endif; ?></section></main></div>
<script>const boxes=[...document.querySelectorAll('.check')],bulk=document.getElementById('bulk'),counter=document.getElementById('selectedCount');function update(){const n=boxes.filter(b=>b.checked).length;if(bulk){bulk.classList.toggle('show',n>0);counter.textContent=n}}boxes.forEach(b=>{b.addEventListener('change',update);b.closest('.card').addEventListener('click',event=>{if(event.target===b)return;b.checked=!b.checked;update()})});</script>
<script>
const cityByState = <?= json_encode($citiesByState, JSON_UNESCAPED_UNICODE) ?>;
const stateFilter = document.querySelector('select[name="state"]');
const cityFilter = document.querySelector('select[name="city"]');
const neighborhoodFilter = document.querySelector('select[name="neighborhood"]');
if (stateFilter && cityFilter) {
    const selectedCity = <?= json_encode($filters['city'], JSON_UNESCAPED_UNICODE) ?>;
    const selectedNeighborhood = <?= json_encode($filters['neighborhood'], JSON_UNESCAPED_UNICODE) ?>;
    async function updateNeighborhoods() {
        if (!neighborhoodFilter) return;
        const city = cityFilter.value;
        if (!city) {
            neighborhoodFilter.innerHTML = '<option value="">Selecione uma cidade primeiro</option>';
            return;
        }
        neighborhoodFilter.innerHTML = '<option value="">Carregando bairros...</option>';
        try {
            const response = await fetch('?ajax=neighborhoods&city=' + encodeURIComponent(city) + '&state=' + encodeURIComponent(stateFilter.value));
            const neighborhoods = await response.json();
            neighborhoodFilter.innerHTML = '<option value="">Todos os bairros encontrados</option>' + neighborhoods.map(neighborhood => '<option value="' + neighborhood.replace(/"/g, '&quot;') + '">' + neighborhood + '</option>').join('');
            if (neighborhoods.includes(selectedNeighborhood)) neighborhoodFilter.value = selectedNeighborhood;
        } catch (error) {
            neighborhoodFilter.innerHTML = '<option value="">Não foi possível carregar bairros</option>';
        }
    }
    async function updateCities() {
        const state = stateFilter.value;
        if (!state) {
            cityFilter.innerHTML = '<option value="">Selecione um estado primeiro</option>';
            return;
        }
        cityFilter.innerHTML = '<option value="">Carregando municípios...</option>';
        try {
            const response = await fetch('https://servicodados.ibge.gov.br/api/v1/localidades/estados/' + state + '/municipios');
            if (!response.ok) throw new Error('IBGE indisponível');
            const municipalities = await response.json();
            municipalities.sort((a, b) => a.nome.localeCompare(b.nome, 'pt-BR'));
            cityFilter.innerHTML = '<option value="">Todas as cidades</option>' + municipalities.map(city => '<option value="' + city.nome.replace(/"/g, '&quot;') + '">' + city.nome + '</option>').join('');
            if (municipalities.some(city => city.nome === selectedCity)) cityFilter.value = selectedCity;
        } catch (error) {
            const fallback = cityByState[state] || [];
            cityFilter.innerHTML = '<option value="">Todas as cidades</option>' + fallback.map(city => '<option value="' + city.replace(/"/g, '&quot;') + '">' + city + '</option>').join('');
        }
    }
    stateFilter.addEventListener('change', () => { cityFilter.value = ''; updateCities(); if (neighborhoodFilter) neighborhoodFilter.innerHTML = '<option value="">Selecione uma cidade primeiro</option>'; });
    cityFilter.addEventListener('change', updateNeighborhoods);
    if (stateFilter.value) updateCities().then(updateNeighborhoods);
}
</script>
</body></html>