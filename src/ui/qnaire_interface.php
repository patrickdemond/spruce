<?php $min=DEVELOPMENT?'':'.min'; ?><!doctype html>
<html ng-app="cenozoApp" ng-controller="LangCtrl" lang="{{ lang }}">
<head ng-controller="HeadCtrl">
  <meta charset="utf-8">
  <title><?php echo APP_TITLE; ?></title>
  <link rel="shortcut icon" href="<?php print ROOT_URL; ?>/img/favicon.ico">
  <link rel="stylesheet" href="<?php print LIB_URL; ?>/bootstrap/dist/css/bootstrap.min.css?build=<?php print CENOZO_BUILD; ?>">
  <link rel="stylesheet" href="<?php print LIB_URL; ?>/fullcalendar/dist/fullcalendar.min.css?build=<?php print CENOZO_BUILD; ?>">
  <link rel="stylesheet" href="<?php print LIB_URL; ?>/angular-bootstrap-colorpicker/css/colorpicker.min.css?build=<?php print CENOZO_BUILD; ?>">
  <link rel="stylesheet" href="<?php print CSS_URL; ?>/cenozo<?php print $min; ?>.css?build=<?php print CENOZO_BUILD; ?>">
  <link rel="stylesheet" href="<?php print ROOT_URL; ?>/css/theme.css?build=<?php print CENOZO_BUILD; ?>">

  <script src="<?php print LIB_URL; ?>/jquery/dist/jquery.min.js?build=<?php print CENOZO_BUILD; ?>"></script>
  <script src="<?php print LIB_URL; ?>/bootstrap/dist/js/bootstrap.min.js?build=<?php print CENOZO_BUILD; ?>"></script>
  <script src="<?php print LIB_URL; ?>/moment/min/moment.min.js?build=<?php print CENOZO_BUILD; ?>"></script>
  <script src="<?php print LIB_URL; ?>/moment-timezone/builds/moment-timezone-with-data-2012-2022.min.js?build=<?php print CENOZO_BUILD; ?>"></script>
  <script src="<?php print LIB_URL; ?>/angular/angular.min.js?build=<?php print CENOZO_BUILD; ?>"></script>
  <script src="<?php print LIB_URL; ?>/angular-ui-bootstrap/dist/ui-bootstrap-tpls.js?build=<?php print CENOZO_BUILD; ?>"></script>
  <script src="<?php print LIB_URL; ?>/angular-animate/angular-animate.min.js?build=<?php print CENOZO_BUILD; ?>"></script>
  <script src="<?php print LIB_URL; ?>/angular-sanitize/angular-sanitize.min.js?build=<?php print CENOZO_BUILD; ?>"></script>
  <script src="<?php print LIB_URL; ?>/@uirouter/angularjs/release/angular-ui-router.min.js?build=<?php print CENOZO_BUILD; ?>"></script>
  <script src="<?php print LIB_URL; ?>/fullcalendar/dist/fullcalendar.min.js?build=<?php print CENOZO_BUILD; ?>"></script>
  <script src="<?php print LIB_URL; ?>/angular-bootstrap-colorpicker/js/bootstrap-colorpicker-module.min.js?build=<?php print CENOZO_BUILD; ?>"></script>
  <script src="<?php print LIB_URL; ?>/chart.js/dist/Chart.min.js?build=<?php print CENOZO_BUILD; ?>"></script>
  <script src="<?php print LIB_URL; ?>/angular-chart.js/dist/angular-chart.min.js?build=<?php print CENOZO_BUILD; ?>"></script>
  <script src="<?php print LIB_URL; ?>/file-saver/dist/FileSaver.min.js?build=<?php print CENOZO_BUILD; ?>"></script>
  <script src="<?php print LIB_URL; ?>/web-audio-recorder-js/lib-minified/WebAudioRecorder.min.js?build=<?php print CENOZO_BUILD; ?>"></script>

  <script src="<?php print CENOZO_URL; ?>/cenozo<?php print $min; ?>.js?build=<?php print CENOZO_BUILD; ?>" id="cenozo"></script>
  <script src="<?php print ROOT_URL; ?>/app<?php print $min; ?>.js?build=<?php print APP_BUILD; ?>" id="app"></script>
  <script src="<?php print LIB_URL; ?>/requirejs/require.js?build=<?php print CENOZO_BUILD; ?>"></script>
  <base href="/"></base>
</head>
<body class="background">
  <div id="root"></div>
  <script>
    // display an error to IE users
    if( window.document.documentMode ) {
      alert(
        'Supported web browsers include Firefox, Chrome, Safari and Edge.  Please do not use Internet Explorer as certain parts of the questionnaire may not display correctly.\n\nLes navigateurs Web pris en charge incluent Firefox, Chrome, Safari et Edge. Veuillez éviter d’utiliser Internet Explorer, car certaines parties du questionnaire pourraient ne pas s’afficher correctement.'
      );
    } else {
      // define the framework and application build numbers
      angular.extend( window.cenozo, {
        build: "<?php print CENOZO_BUILD; ?>",
        baseUrl: "<?php print CENOZO_URL; ?>",
        libUrl: "<?php print LIB_URL; ?>",
        cssUrl: "<?php print CSS_URL; ?>",
        development: <?php print DEVELOPMENT ? 'true' : 'false'; ?>
      } );
      angular.extend( window.cenozoApp, {
        build: "<?php print APP_BUILD; ?>",
        baseUrl: "<?php print ROOT_URL; ?>"
      } );

      // determine whether we are in development mode
      if( window.cenozo.development ) console.info( 'Development mode' );

      // define framework modules, set the applications module list then route them all
      window.cenozo.defineFrameworkModules( <?php print $framework_module_string; ?> );
      window.cenozoApp.setModuleList( <?php print $module_string; ?> );
      window.cenozoApp.config( [
        '$stateProvider',
        function( $stateProvider ) {
          for( var module in window.cenozoApp.moduleList )
            window.cenozo.routeModule( $stateProvider, module, window.cenozoApp.moduleList[module] );
        }
      ] );

      window.cenozoApp.controller( 'LangCtrl', [
        '$scope',
        function( $scope ) {
          $scope.lang = 'en';
          window.cenozoApp.setLang = function( lang ) { $scope.lang = lang; }
        }
      ] );

      window.cenozoApp.controller( 'HeadCtrl', [
        '$scope', 'CnSession',
        function( $scope, CnSession ) {
          $scope.getPageTitle = function() { return CnSession.pageTitle; };
        }
      ] );
    }

    // determine if the browser is compatible
    var root = document.getElementById("root");
    var matches = navigator.userAgent.match( "OS ([0-9]+)_[0-9_]+ like Mac OS X" );
    if( true || window.document.documentMode || (null != matches && 12 > parseInt(matches[1])) ) {
      root.innerHTML = 
        '<div class="container-fluid headerless-outer-view-frame fade-transition">\n' +
        '  <div class="inner-view-frame">\n' +
        '    <div class="container-fluid bg-white">\n' +
        '      <h3 class="text-primary"><?php print $incompatible_title; ?></h3>\n' +
        '      <div class="container-fluid">\n' +
        '      <blockquote><?php print $incompatible_message; ?></blockquote>\n' +
        '    </div>\n' +
        '  </div>\n' +
        '</div>\n';
    }
  </script>
</body>
</html>
