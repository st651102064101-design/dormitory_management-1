<?php
// Notes for fix plan
// Currently $completionCondition either filters out completed or pending.
// To do client-side filtering, we need to load ALL tenants (both pending and completed),
// but ONLY if bkg_id is not specified (which we don't care, we just want to load all regardless of $_GET['completed']).
// Then in the view, we render `data-wiz-group` based on the SAME logic PHP uses in SQL.
