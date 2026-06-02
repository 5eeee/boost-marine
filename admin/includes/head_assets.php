<?php
/**
 * Общие ресурсы <head> для админ-панели (без FOUC)
 */
$adminCssVer = defined('ASSET_VERSION') ? ASSET_VERSION : '1';
$adminJsVer = $adminCssVer;
?>
<style id="admin-critical">
:root{--accent:#0ea5e9;--dark:#1e1e2f;--dark-light:#2d2d3a;--dark-card:#262636;--text:#f0f0f0;--text-light:#ccc;--text-muted:#999;--border:#404040;--sidebar-width:280px}
*{margin:0;padding:0;box-sizing:border-box}
html{background:#1e1e2f}
body{font-family:Montserrat,system-ui,sans-serif;background:#1e1e2f;color:#f0f0f0;line-height:1.5;min-height:100vh}
.app-container{display:flex;min-height:100vh}
.sidebar{width:280px;position:fixed;top:0;left:0;bottom:0;z-index:1000;padding:20px 0 20px 20px}
.sidebar__inner{background:#2d2d3a;border-radius:30px;border:1px solid #404040;height:100%;display:flex;flex-direction:column;overflow:hidden}
.main-content{flex:1;margin-left:280px;padding:30px;background:#1e1e2f;min-height:100vh;display:flex;flex-direction:column}
</style>
<link rel="preload" href="/assets/css/admin.css?v=<?php echo htmlspecialchars($adminCssVer); ?>" as="style">
<link rel="stylesheet" href="/assets/css/admin.css?v=<?php echo htmlspecialchars($adminCssVer); ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" media="print" onload="this.media='all'">
<noscript><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"></noscript>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" media="print" onload="this.media='all'">
<noscript><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"></noscript>
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css" media="print" onload="this.media='all'">
<link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.dataTables.min.css" media="print" onload="this.media='all'">
