(function ($) {
  $(document).ready(function() {
    $('input[name="warehouse"]').on('change', function(evt) {
      if (evt.target.value=='other') {
        $('#warehouse_details').attr('open', true);
      } else {
        $('#warehouse_details').removeAttr('open');
      }
    });
  });
  // Trap moving the map, and store the centre + zoom in hidden controls
  mapInitialisationHooks.push(function(div) {
    div.map.events.on({
      'moveend' : function(evt) {
        var centre = div.map.center.clone();
        centre.transform(div.map.projection, new OpenLayers.Projection('EPSG:4326'));
        $('#edit-map-centroid-lat').val(centre.lat);
        $('#edit-map-centroid-long').val(centre.lon);
        $('#edit-map-zoom').val(div.map.zoom);
      }
    });
  });
})(jQuery);