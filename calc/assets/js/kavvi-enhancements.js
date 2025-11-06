(function(){
  const basePath = (window.APP_BASE_PATH || '/calc').replace(/\/$/, '');
  window.asset = function(path){
    return basePath + '/' + String(path || '').replace(/^\//,'');
  };
})();
