var elSkladbaNazev = $('#hledaniSkladby #nazev');
var elSkladbaId    = $('#hledaniSkladby #id');
var elButton       = $('#hledaniSkladby button');

elSkladbaNazev.typeahead({
  prefetch: {
    url:  elSkladbaId.data('seznam-skladeb')
  },
  limit: 10
});
elSkladbaNazev.on('typeahead:selected', function (object, datum) {
  elSkladbaId.val(datum['id']);
});
elButton.click(function () {
  var id = elSkladbaId.val();
  var url = '';
  if(id) {
    url = elSkladbaId.data('skladba-detail');
    url = url.replace('xxx', id);
  }
  else {
    url = elSkladbaId.data('skladba-filtr');
    url = url.replace('xxx', elSkladbaNazev.val());
  }
  window.location = url;
});
elSkladbaNazev.keypress(function(e) {
  if(e.which == 13) { //enter
    elButton.click();
  }
});
