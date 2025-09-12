<?php
function spi_panel_comercio_dashboard_shortcode() {
    if ($msg = spi_wallet_require_subscriber()) {
        return $msg;
    }

    global $wpdb;
    $user_id = get_current_user_id();
    $tabla_clientes = $wpdb->prefix . 'spi_wallet_clientes';
    $tabla_logs     = $wpdb->prefix . 'spi_wallet_logs';

    // ===== Entrada: filtros, pesta09as y paginaci¨®n =====
    $filtro_cliente = isset($_GET['filtro_cliente']) ? sanitize_text_field($_GET['filtro_cliente']) : '';
    $filtro_codigo  = isset($_GET['filtro_codigo'])  ? sanitize_text_field($_GET['filtro_codigo'])  : '';
    $filtro_desde   = isset($_GET['filtro_desde'])   ? sanitize_text_field($_GET['filtro_desde'])   : '';
    $filtro_hasta   = isset($_GET['filtro_hasta'])   ? sanitize_text_field($_GET['filtro_hasta'])   : '';
    $active_tab     = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : ''; // t-clientes | t-historial | t-metricas

    // Filtro de rango para m¨¦tricas
    $range = isset($_GET['range']) ? sanitize_text_field($_GET['range']) : 'month'; // week|month|half|year
    $now   = current_time('timestamp'); // WP TZ
    switch ($range) {
        case 'week':  $start_ts = strtotime('-6 days', $now); break;
        case 'half':  $start_ts = strtotime('-6 months', $now); break;
        case 'year':  $start_ts = strtotime('-12 months', $now); break;
        case 'month':
        default:      $start_ts = strtotime('-30 days', $now); break;
    }
    $start_date = gmdate('Y-m-d 00:00:00', $start_ts);
    $end_date   = gmdate('Y-m-d 23:59:59', $now);

    $per_page   = 10;
    $page_cli   = max(1, isset($_GET['page_cli']) ? intval($_GET['page_cli']) : 1);
    $page_log   = max(1, isset($_GET['page_log']) ? intval($_GET['page_log']) : 1);
    $offset_cli = ($page_cli - 1) * $per_page;
    $offset_log = ($page_log - 1) * $per_page;

    // ===== Helpers =====
    $build_url = function(array $overrides = []) {
        $args = array_merge($_GET, $overrides);
        foreach ($args as $k => $v) { if ($v === '' || $v === null) unset($args[$k]); }
        return esc_url(add_query_arg($args));
    };

    // ===== Clientes (count + page) =====
    $where_cli  = ' WHERE comercio_id = %d ';
    $params_cli = [$user_id];
    if ($filtro_cliente !== '') {
        $like = '%' . $wpdb->esc_like($filtro_cliente) . '%';
        $where_cli .= " AND (nombre LIKE %s OR correo LIKE %s OR codigo_qr LIKE %s) ";
        $params_cli = array_merge($params_cli, [$like, $like, $like]);
    }
    $total_cli = (int) $wpdb->get_var(
        $wpdb->prepare("SELECT COUNT(*) FROM $tabla_clientes $where_cli", $params_cli)
    );
    $total_pages_cli = max(1, (int) ceil($total_cli / $per_page));

    $sql_cli   = "SELECT * FROM $tabla_clientes $where_cli ORDER BY id DESC LIMIT %d OFFSET %d";
    $clientes = $wpdb->get_results(
        $wpdb->prepare($sql_cli, array_merge($params_cli, [$per_page, $offset_cli]))
    );

    // ===== Historial (count + page) =====
    $where_log  = " WHERE comercio_id = %d ";
    $params_log = [$user_id];
    if ($filtro_desde && $filtro_hasta) {
        $where_log  .= " AND fecha BETWEEN %s AND %s ";
        $params_log[] = $filtro_desde . ' 00:00:00';
        $params_log[] = $filtro_hasta . ' 23:59:59';
    }
    if ($filtro_codigo) {
        $where_log  .= " AND codigo_qr LIKE %s ";
        $params_log[] = '%' . $wpdb->esc_like($filtro_codigo) . '%';
    }

    $total_log = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $tabla_logs $where_log", ...$params_log));
    $total_pages_log = max(1, (int) ceil($total_log / $per_page));

    $sql_log = "SELECT fecha, codigo_qr FROM $tabla_logs $where_log ORDER BY fecha DESC LIMIT %d OFFSET %d";
    $logs = $wpdb->get_results($wpdb->prepare($sql_log, ...array_merge($params_log, [$per_page, $offset_log])));

    // ===== M¨¦tricas (rango seleccionado) =====
    // Serie diaria (fecha -> conteo)
    $rows_dias = $wpdb->get_results($wpdb->prepare(
        "SELECT DATE(fecha) d, COUNT(*) c
         FROM $tabla_logs
         WHERE comercio_id = %d AND fecha BETWEEN %s AND %s
         GROUP BY DATE(fecha)
         ORDER BY DATE(fecha) ASC",
        $user_id, $start_date, $end_date
    ), ARRAY_A);

    // Normaliza d¨ªas faltantes a 0
    $labels_dias = [];
    $data_dias   = [];
    $cursor = strtotime(gmdate('Y-m-d', $start_ts));
    $endDay = strtotime(gmdate('Y-m-d', $now));
    $by_day = [];
    foreach ($rows_dias as $r) { $by_day[$r['d']] = (int)$r['c']; }
    while ($cursor <= $endDay) {
        $k = gmdate('Y-m-d', $cursor);
        $labels_dias[] = date_i18n('d M', $cursor);
        $data_dias[]   = isset($by_day[$k]) ? $by_day[$k] : 0;
        $cursor = strtotime('+1 day', $cursor);
    }

    // Distribuci¨®n por hora (0-23) en el rango
    $rows_horas = $wpdb->get_results($wpdb->prepare(
        "SELECT HOUR(fecha) h, COUNT(*) c
         FROM $tabla_logs
         WHERE comercio_id = %d AND fecha BETWEEN %s AND %s
         GROUP BY HOUR(fecha)
         ORDER BY h ASC",
        $user_id, $start_date, $end_date
    ), ARRAY_A);
    $labels_horas = range(0,23);
    $data_horas   = array_fill(0,24,0);
    foreach ($rows_horas as $r) {
        $h = (int)$r['h']; $data_horas[$h] = (int)$r['c'];
    }

    ob_start();
    ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
      /* ===== Parche scroll: anulamos altura fija del host ===== */
      .shortcode-container { height:auto !important; display:block !important; place-items:unset !important; }

      /* ===== Scope y layout ===== */
      #spi-shortcode {
        --font: 'Inter', system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif;
        --surface: #ffffff;
        --text: #1f2937;
        --muted: #6b7280;
        --primary: #111827;
        --accent: #0284c7;
        --accent-2: #10b981;
        --ring: rgba(2,132,199,.25);
        --shadow: 0 8px 20px rgba(0,0,0,.06);

        width: 100%;
        box-sizing: border-box;
        font-family: var(--font);
        color: var(--text);
        display: flex;
        flex-direction: column;
        gap: 16px;
        padding-bottom: 16px; /* evita cortes al final */
      }
      body.dark #spi-shortcode {
        --surface: #1f2937;
        --text: #e5e7eb;
        --muted: #9ca3af;
        --primary: #f3f4f6;
        --accent: #7dd3fc;
        --accent-2: #6ee7b7;
        --ring: rgba(125,211,252,.25);
        --shadow: 0 10px 24px rgba(0,0,0,.35);
      }

      /* NAV tipo segmented */
      #spi-shortcode .segmented {
        display: inline-flex;
        background: linear-gradient(to right, rgba(2,132,199,.08), rgba(16,185,129,.08));
        padding: 6px; border-radius: 14px; gap: 6px; box-shadow: var(--shadow);
        align-self: flex-start;
      }
      #spi-shortcode .segmented button[role="tab"] {
        appearance: none; border: 0; cursor: pointer;
        background: transparent; color: var(--muted);
        padding: 10px 14px; border-radius: 10px;
        display: inline-flex; align-items: center; gap: 8px;
        font-weight: 700; letter-spacing: .2px;
        transition: transform .18s ease, background .25s ease, color .25s ease;
      }
      #spi-shortcode .segmented button[aria-selected="true"] {
        background: var(--surface); color: var(--primary); box-shadow: 0 0 0 2px var(--ring) inset;
      }

      /* Panel */
      #spi-shortcode .panel {
        background: var(--surface);
        border-radius: 16px; box-shadow: var(--shadow);
        padding: clamp(14px, 2vw, 20px);
      }

      /* Filtros tabla */
      #spi-shortcode .filters { display: grid; grid-template-columns: 1fr auto; gap: 10px; margin-bottom: 12px; }
      #spi-shortcode .filters .inputs { display: grid; grid-template-columns: repeat(3, minmax(0,1fr)); gap: 10px; }
      #spi-shortcode .input { position: relative; }
      #spi-shortcode .input input {
        width: 100%; background: transparent; color: var(--text);
        border-radius: 12px; border: 1px solid rgba(0,0,0,.12); padding: 10px 12px;
        outline: none; transition: box-shadow .2s ease, border-color .2s ease, background .2s ease;
      }
      body.dark #spi-shortcode .input input { border-color: rgba(255,255,255,.12); }
      #spi-shortcode .input input:focus { box-shadow: 0 0 0 4px var(--ring); border-color: var(--accent); background: rgba(2,132,199,.035); }

      #spi-shortcode .btn {
        appearance: none; border: 0; cursor: pointer;
        background: linear-gradient(135deg, var(--accent), var(--accent-2));
        color: #fff; border-radius: 12px; padding: 10px 14px; font-weight: 800; letter-spacing: .3px;
        display: inline-flex; align-items: center; gap: 8px; transition: transform .18s ease, filter .2s ease;
      }
      #spi-shortcode .btn:hover { filter: brightness(.95); transform: translateY(-1px); }

      /* Tabla */
      #spi-shortcode .table-wrap {
        width: 100%; overflow: auto; border-radius: 14px; border: 1px solid rgba(0,0,0,.06);
      }
      body.dark #spi-shortcode .table-wrap { border-color: rgba(255,255,255,.06); }

      #spi-shortcode table { width: 100%; border-collapse: collapse; font-size: 1rem; }
      #spi-shortcode thead th {
        position: sticky; top: 0; z-index: 1;
        background: linear-gradient(to bottom, rgba(2,132,199,.10), rgba(2,132,199,.06));
        color: var(--primary); text-align: left; padding: 12px; font-weight: 800;
      }
      #spi-shortcode tbody td { padding: 12px; border-top: 1px solid rgba(0,0,0,.06); }
      body.dark #spi-shortcode tbody td { border-color: rgba(255,255,255,.06); }
      #spi-shortcode tbody tr:hover { background: rgba(2,132,199,.06); }

      #spi-shortcode .badge-soft {
        background: rgba(16,185,129,.12); color: var(--accent-2);
        border-radius: 999px; padding: 4px 10px; font-weight: 800;
      }

      /* Paginaci¨®n (estilo y truncada ligera) */
      #spi-shortcode .pagination { display:flex; gap:8px; align-items:center; flex-wrap:wrap; margin-top:14px; }
      #spi-shortcode .pagination a, #spi-shortcode .pagination span {
        text-decoration:none; padding:8px 14px; border-radius:10px; font-weight:700; font-size:.95rem; letter-spacing:.3px; transition:.25s;
        box-shadow:0 2px 6px rgba(0,0,0,.08); background: var(--surface); color: var(--primary); border:1px solid rgba(0,0,0,.12);
      }
      #spi-shortcode .pagination a:hover { background: rgba(2,132,199,.08); }
      #spi-shortcode .pagination .current { border:2px solid var(--accent); box-shadow:0 0 0 2px var(--ring) inset; }

      /* M¨¦tricas */
      #spi-shortcode .metrics-controls { display:flex; gap:8px; flex-wrap:wrap; margin-bottom:8px; }
      #spi-shortcode .chip {
        border:1px solid rgba(0,0,0,.12); border-radius:999px; padding:6px 12px; cursor:pointer; background:var(--surface); color:var(--primary); font-weight:700;
        text-decoration:none; display:inline-flex; align-items:center; gap:6px;
      }
      #spi-shortcode .chip.active { border-color: var(--accent); box-shadow: 0 0 0 2px var(--ring) inset; }

      #spi-shortcode .metrics-grid { display:grid; grid-template-columns: 1fr; gap:16px; }
      @media (min-width: 1024px) {
        #spi-shortcode .metrics-grid { grid-template-columns: 1fr 1fr; }
      }
      #spi-shortcode .chart-box {
        background: var(--surface); border-radius: 16px; box-shadow: var(--shadow); padding: 12px;
        height: 320px; /* PC */
      }
      @media (max-width: 820px) { #spi-shortcode .chart-box { height: 240px; } }

      /* Responsive tablas -> cards en m¨®vil */
      #spi-shortcode .cards { display:none; }
      @media (max-width: 820px) {
        #spi-shortcode .filters { grid-template-columns: 1fr; }
        #spi-shortcode .filters .inputs { grid-template-columns: 1fr; }
        #spi-shortcode table { display:none; }
        #spi-shortcode .table-wrap { border:0; }
        #spi-shortcode .cards { display:grid; gap:10px; }
        #spi-shortcode .card-row {
          background: var(--surface); border:1px solid rgba(0,0,0,.06); border-radius:14px; padding:12px; box-shadow: var(--shadow);
        }
        #spi-shortcode .card-row .item { display:grid; grid-template-columns: 120px 1fr; padding:6px 0; }
        #spi-shortcode .card-row .item .label { color: var(--muted); font-weight:700; }
      }
    </style>

    <div id="spi-shortcode" aria-label="Panel de Comercio - Contenido">
      <!-- NAV -->
      <div class="segmented" role="tablist" aria-label="Secciones">
        <button type="button" class="segbtn" role="tab" aria-selected="true" aria-controls="tab-clientes" id="t-clientes">Clientes</button>
        <button type="button" class="segbtn" role="tab" aria-selected="false" aria-controls="tab-historial" id="t-historial">Historial</button>
        <button type="button" class="segbtn" role="tab" aria-selected="false" aria-controls="tab-metricas" id="t-metricas">M&eacute;tricas</button>
      </div>

      <!-- CLIENTES -->
      <section class="panel" id="tab-clientes" role="tabpanel" aria-labelledby="t-clientes">
        <h2 class="title">Listado de clientes</h2>

        <form method="get" class="filters" novalidate>
          <div class="inputs">
            <div class="input">
              <label class="visually-hidden" for="filtro_cliente">Buscar</label>
              <input type="text" id="filtro_cliente" name="filtro_cliente" placeholder="Nombre, correo o c&oacute;digo" value="<?php echo esc_attr($filtro_cliente); ?>">
            </div>
          </div>
          <button class="btn">Filtrar</button>
          <input type="hidden" name="tab" value="t-clientes">
        </form>

        <div class="table-wrap">
          <table aria-label="Clientes">
            <thead>
              <tr>
                <th>Nombre</th>
                <th>Correo</th>
                <th>Tel&eacute;fono</th>
                <th>Sellos</th>
                <th>C&oacute;digo&nbsp;QR</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!empty($clientes)): foreach ($clientes as $c): ?>
                <tr>
                  <td><?php echo esc_html($c->nombre); ?></td>
                  <td><?php echo esc_html($c->correo); ?></td>
                  <td><?php echo esc_html($c->telefono); ?></td>
                  <td><span class="badge-soft"><?php echo (int) $c->sellos; ?></span></td>
                  <td><?php echo esc_html($c->codigo_qr); ?></td>
                </tr>
              <?php endforeach; else: ?>
                <tr><td colspan="5" class="muted">Sin resultados</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

        <!-- cards m¨®vil -->
        <div class="cards" aria-hidden="true">
          <?php if (!empty($clientes)): foreach ($clientes as $c): ?>
            <div class="card-row">
              <div class="item"><div class="label">Nombre</div><div><?php echo esc_html($c->nombre); ?></div></div>
              <div class="item"><div class="label">Correo</div><div><?php echo esc_html($c->correo); ?></div></div>
              <div class="item"><div class="label">Tel&eacute;fono</div><div><?php echo esc_html($c->telefono); ?></div></div>
              <div class="item"><div class="label">Sellos</div><div><span class="badge-soft"><?php echo (int) $c->sellos; ?></span></div></div>
              <div class="item"><div class="label">C&oacute;digo QR</div><div><?php echo esc_html($c->codigo_qr); ?></div></div>
            </div>
          <?php endforeach; else: ?>
            <div class="muted">Sin resultados</div>
          <?php endif; ?>
        </div>

        <!-- Paginaci¨®n Clientes (truncada) -->
        <?php if ($total_pages_cli > 1): ?>
          <nav class="pagination" aria-label="Paginaci&oacute;n de clientes">
            <?php
              $prev = max(1, $page_cli - 1);
              $next = min($total_pages_cli, $page_cli + 1);
              if ($page_cli > 1) echo '<a href="'.$build_url(['page_cli'=>$prev,'tab'=>'t-clientes']).'">&laquo; Anterior</a>';

              $range = 2; $ellipsis=false;
              for ($i=1; $i<=$total_pages_cli; $i++) {
                if ($i==1 || $i==$total_pages_cli || ($i >= $page_cli-$range && $i <= $page_cli+$range)) {
                  if ($i === $page_cli) echo '<span class="current">'.$i.'</span>';
                  else echo '<a href="'.$build_url(['page_cli'=>$i,'tab'=>'t-clientes']).'">'.$i.'</a>';
                  $ellipsis=false;
                } else { if (!$ellipsis) { echo '<span class="dots">¡­</span>'; $ellipsis=true; } }
              }

              if ($page_cli < $total_pages_cli) echo '<a href="'.$build_url(['page_cli'=>$next,'tab'=>'t-clientes']).'">Siguiente &raquo;</a>';
            ?>
          </nav>
        <?php endif; ?>
      </section>

      <!-- HISTORIAL -->
      <section class="panel" id="tab-historial" role="tabpanel" aria-labelledby="t-historial" hidden>
        <h2 class="title">Historial de escaneos</h2>

        <form method="get" class="filters" novalidate>
          <div class="inputs">
            <div class="input">
              <label class="visually-hidden" for="filtro_desde">Desde</label>
              <input type="date" id="filtro_desde" name="filtro_desde" value="<?php echo esc_attr($filtro_desde); ?>">
            </div>
            <div class="input">
              <label class="visually-hidden" for="filtro_hasta">Hasta</label>
              <input type="date" id="filtro_hasta" name="filtro_hasta" value="<?php echo esc_attr($filtro_hasta); ?>">
            </div>
            <div class="input">
              <label class="visually-hidden" for="filtro_codigo">C&oacute;digo QR</label>
              <input type="text" id="filtro_codigo" name="filtro_codigo" placeholder="C&oacute;digo QR" value="<?php echo esc_attr($filtro_codigo); ?>">
            </div>
          </div>
          <button class="btn">Filtrar</button>
          <input type="hidden" name="tab" value="t-historial">
        </form>

        <div class="table-wrap">
          <table aria-label="Historial">
            <thead>
              <tr>
                <th>Fecha</th>
                <th>C&oacute;digo&nbsp;QR</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!empty($logs)): foreach ($logs as $l): ?>
                <tr>
                  <td><?php echo esc_html($l->fecha); ?></td>
                  <td><?php echo esc_html($l->codigo_qr); ?></td>
                </tr>
              <?php endforeach; else: ?>
                <tr><td colspan="2" class="muted">Sin registros</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

        <!-- cards m¨®vil -->
        <div class="cards" aria-hidden="true">
          <?php if (!empty($logs)): foreach ($logs as $l): ?>
            <div class="card-row">
              <div class="item"><div class="label">Fecha</div><div><?php echo esc_html($l->fecha); ?></div></div>
              <div class="item"><div class="label">C&oacute;digo QR</div><div><?php echo esc_html($l->codigo_qr); ?></div></div>
            </div>
          <?php endforeach; else: ?>
            <div class="muted">Sin registros</div>
          <?php endif; ?>
        </div>

        <!-- Paginaci¨®n Historial (truncada) -->
        <?php if ($total_pages_log > 1): ?>
          <nav class="pagination" aria-label="Paginaci&oacute;n de historial">
            <?php
              $prev = max(1, $page_log - 1);
              $next = min($total_pages_log, $page_log + 1);
              if ($page_log > 1) echo '<a href="'.$build_url(['page_log'=>$prev,'tab'=>'t-historial']).'">&laquo; Anterior</a>';

              $range = 2; $ellipsis=false;
              for ($i=1; $i<=$total_pages_log; $i++) {
                if ($i==1 || $i==$total_pages_log || ($i >= $page_log-$range && $i <= $page_log+$range)) {
                  if ($i === $page_log) echo '<span class="current">'.$i.'</span>';
                  else echo '<a href="'.$build_url(['page_log'=>$i,'tab'=>'t-historial']).'">'.$i.'</a>';
                  $ellipsis=false;
                } else { if (!$ellipsis) { echo '<span class="dots">¡­</span>'; $ellipsis=true; } }
              }

              if ($page_log < $total_pages_log) echo '<a href="'.$build_url(['page_log'=>$next,'tab'=>'t-historial']).'">Siguiente &raquo;</a>';
            ?>
          </nav>
        <?php endif; ?>
      </section>

      <!-- M07TRICAS -->
      <section class="panel" id="tab-metricas" role="tabpanel" aria-labelledby="t-metricas" hidden>
        <h2 class="title">M&eacute;tricas</h2>

        <!-- Filtros de rango -->
        <div class="metrics-controls">
          <?php
            $ranges = [
              'week'  => 'Semana',
              'month' => 'Mes',
              'half'  => '6&nbsp;meses',
              'year'  => 'A&ntilde;o',
            ];
            foreach ($ranges as $key=>$label) {
              $is = ($range === $key) ? 'active' : '';
              echo '<a class="chip '.$is.'" href="'.$build_url(['range'=>$key,'tab'=>'t-metricas']).'">'.$label.'</a>';
            }
          ?>
        </div>

        <div class="metrics-grid">
          <!-- Serie diaria -->
          <div class="chart-box">
            <canvas id="chart_daily"></canvas>
          </div>
          <!-- Por hora del d¨ªa -->
          <div class="chart-box">
            <canvas id="chart_hourly"></canvas>
          </div>
        </div>
      </section>
    </div>

    <script>
      (function() {
        // ===== Tabs sin Bootstrap (y respetando ?tab=) =====
        const tabs = [
          {btn: 't-clientes', panel: 'tab-clientes'},
          {btn: 't-historial', panel: 'tab-historial'},
          {btn: 't-metricas', panel: 'tab-metricas'},
        ];
        function showTab(id) {
          tabs.forEach(t => {
            const b = document.getElementById(t.btn);
            const p = document.getElementById(t.panel);
            const active = (t.btn === id);
            b.setAttribute('aria-selected', active ? 'true' : 'false');
            p.hidden = !active;
          });
          localStorage.setItem('spi_tab', id);
        }
        const urlParams = new URLSearchParams(window.location.search);
        const urlTab = urlParams.get('tab');
        const saved = localStorage.getItem('spi_tab');
        const initial = (urlTab && document.getElementById(urlTab)) ? urlTab : (saved && document.getElementById(saved) ? saved : 't-clientes');
        showTab(initial);
        tabs.forEach(t => { document.getElementById(t.btn).addEventListener('click', () => showTab(t.btn)); });

        // ===== Charts =====
        const dark = document.body.classList.contains('dark');
        const colorPrimary  = dark ? '#7dd3fc' : '#0284c7';
        const colorSecondary= dark ? '#a7f3d0' : '#10b981';
        const gridColor     = dark ? 'rgba(255,255,255,.12)' : 'rgba(0,0,0,.08)';
        const fontColor     = dark ? '#e5e7eb' : '#1f2937';

        // Serie diaria (bar/line combo)
        const c1 = document.getElementById('chart_daily');
        if (c1) {
          const labels = <?php echo wp_json_encode($labels_dias, JSON_UNESCAPED_UNICODE); ?>;
          const data   = <?php echo wp_json_encode($data_dias); ?>;
          new Chart(c1.getContext('2d'), {
            type: 'bar',
            data: { labels, datasets: [{ label: 'Escaneos', data, backgroundColor: colorPrimary, borderRadius: 4 }] },
            options: {
              responsive: true, maintainAspectRatio: false,
              scales: { x: { grid:{color:gridColor}, ticks:{ color: fontColor } }, y: { beginAtZero:true, grid:{color:gridColor}, ticks:{ color: fontColor, precision:0 } } },
              plugins: { legend:{ display:false }, tooltip:{ mode:'index', intersect:false } },
              animation: { duration: 260, easing: 'easeOutCubic' }
            }
          });
        }

        // Distribuci¨®n por hora (0-23)
        const c2 = document.getElementById('chart_hourly');
        if (c2) {
          const labels = <?php echo wp_json_encode(array_map(function($h){ return sprintf('%02d:00', $h); }, $labels_horas)); ?>;
          const data   = <?php echo wp_json_encode($data_horas); ?>;
          new Chart(c2.getContext('2d'), {
            type: 'line',
            data: { labels, datasets: [{ label:'Por hora', data, borderColor: colorSecondary, backgroundColor: colorSecondary, fill:false, tension:.3 }] },
            options: {
              responsive: true, maintainAspectRatio: false,
              scales: { x: { grid:{color:gridColor}, ticks:{ color: fontColor } }, y: { beginAtZero:true, grid:{color:gridColor}, ticks:{ color: fontColor, precision:0 } } },
              plugins: { legend:{ display:false }, tooltip:{ mode:'nearest', intersect:false } },
              animation: { duration: 260, easing: 'easeOutCubic' }
            }
          });
        }
      })();
    </script>
    <?php
    return ob_get_clean();
}