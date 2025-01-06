<!doctype html>
<html ng-app="cenozoApp" ng-controller="LangCtrl" lang="{{ lang }}">
<head ng-controller="HeadCtrl">
  <meta charset="utf-8">
  <title><?php echo APP_TITLE; ?></title>
<?php $this->print_libs(); ?>
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
      window.cenozo.defineFrameworkModules( <?php $this->print_list( 'framework_modules' ); ?> );
      window.cenozoApp.setModuleList( <?php $this->print_list( 'modules' ); ?> );
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
    if( window.document.documentMode || (null != matches && 13 > parseInt(matches[1])) ) {
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
    } else {
      root.innerHTML =
        '<div id="view" ui-view class="container-fluid headerless-outer-view-frame fade-transition noselect"></div>';
    }
  </script>
</body>
</html>
