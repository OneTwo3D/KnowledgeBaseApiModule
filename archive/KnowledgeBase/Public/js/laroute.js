(function () {
    var module_routes = [
    {
        "uri": "knowledge-base\/ajax-admin",
        "name": "mailboxes.knowledgebase.ajax_admin"
    },
    {
        "uri": "knowledge-base\/ajax_html\/{action}",
        "name": "knowledgebase.ajax_html"
    }
];

    if (typeof(laroute) != "undefined") {
        laroute.add_routes(module_routes);
    } else {
        contole.log('laroute not initialized, can not add module routes:');
        contole.log(module_routes);
    }
})();