
$("tr.expandable").on('click', function(e) {
    var target = $(e.target);
    if (target.is("button") || target.is("a") || target.is("input")) {
        return;
    }

    var button = $(this).find("button.btnExpand");
    button.trigger("click");
});

$("button.btnExpand").click(function() {
    var pi_wrapper = $(this).parent();  // parent column (td)
    var piRow = pi_wrapper.parent();  // parent row (tr)
    var piTree = piRow.parent();  // parent table (table)
    var pi_uid = pi_wrapper.next().html();

    if ($(this).hasClass("btnExpanded")) {
        // already expanded
        var currentNode = piRow.nextAll().first();

        while (!currentNode.hasClass("expandable") && currentNode.length != 0) {
            var nextNode = currentNode.nextAll().first();
            currentNode.remove();
            currentNode = nextNode;
        }

        $(this).removeClass("btnExpanded");
        piRow.removeClass("expanded");
        piRow.removeClass("first");
    } else {
        $("button.btnExpanded").trigger("click");
        // not expanded
        $.ajax({
            url: ajax_url + pi_uid,
            success: function(result) {
                piRow.after(result);
            }
        });

        $(this).addClass("btnExpanded");
        piRow.addClass("expanded");
        piRow.addClass("first");
    }
});