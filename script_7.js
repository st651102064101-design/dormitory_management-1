
(function(){
  try {
    var mode = "list";
    if (mode !== 'grid' && mode !== 'list') { mode = 'grid'; }
    localStorage.setItem('adminDefaultViewMode', mode);
  } catch (e) {}
})();
