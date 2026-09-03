// SabNZBd actions
function sabBaseURL() {
    var apikey = $("div.sabnzbdPanel input.apikey").val();
    return createBaseURL()+'?page=nzbhandlerapi&nzbhandlerapikey='+apikey;
}

function sabActions(start,limit,action,slot) {
    var baseURL = sabBaseURL();

    if(action == 'pause') {
        var url = baseURL+'&action=pause&id'+slot;
        $.get(url, function(){
            updateSabPanel(start,limit);
        });
    } else if(action == 'resume') {
        var url = baseURL+'&action=resume&id'+slot;
        $.get(url, function(){
            updateSabPanel(start,limit);
        });
    } else if(action == 'speedlimit') {
        var limit = $("td.speedlimit input[name=speedLimit]").val();
        var url = baseURL+'&action=setspeedlimit&limit='+limit;
        $.get(url, function(){
            updateSabPanel(start,limit);
        });
    } else if(action == 'up') {
        var url = baseURL+'&action=moveup&id='+slot;
        $.get(url, function(){
            updateSabPanel(start,limit);
        });
    } else if(action == 'down') {
        var url = baseURL+'&action=movedown&id='+slot;
        $.get(url, function(){
            updateSabPanel(start,limit);
        });
    } else if(action == 'delete') {
        var url = baseURL+'&action=delete&id='+slot;
        $.get(url, function(){
            updateSabPanel(start,limit);
        });
    } else 	if(action == 'pausequeue') {
        var url = baseURL+'&action=pausequeue';
        $.get(url, function(){
            updateSabPanel(start,limit);
        });
    } else if(action == 'resumequeue') {
        var url = baseURL+'&action=resumequeue';
        $.get(url, function(){
            updateSabPanel(start,limit);
        });
    }
}

function drawGraph(currentSpeed,interval) {
    var numXLabels = 8;
    var numYLabels = 5;

    if($("table.sabGraphData tbody > tr").size() == 1) {
        // maak juiste hoeveelheid data rijen aan (afhankelijk van numXLabels
        $("table.sabGraphData").empty();
        i = 0;
        for (i = 0; i <= numXLabels; i++) {
            $("table.sabGraphData").append("<tr><td>0.00</td></tr>");
        }
    }
    // vul de juiste rijen met de juiste data
    if($("table.sabGraphData td:empty").size() != 0) {
        $("table.sabGraphData td:empty").first().html(currentSpeed);
    } else {
        $("table.sabGraphData td").first().remove();
        $("table.sabGraphData").append("<tr><td>"+currentSpeed+"</td></tr>");
    }

    var elem = $("canvas#graph");
    elem.width = $("canvas#graph").width();
    elem.height = $("canvas#graph").height();
    var offset = {
        "top": 6,
        "right": 6,
        "bottom": 18,
        "left": 30
    };
    var graph = {
        "width": elem.width - offset.right - offset.left,
        "height": elem.height - offset.bottom - offset.top
    };
    var axisSpacing = {
        "x": 8,
        "y": 6
    };
    var intervalWidth = (elem.width - offset.left - offset.right) / numXLabels;

    var context = elem[0].getContext("2d");

    var speed = new Array();
    $("table.sabGraphData td").each(function(){
        speed.push({
            "count": $(this).index(),
            "value": $(this).text()
        });
    });
    var maxspeed = 0;
    for (var i = 0; i <= numXLabels; i++) {
        if(Math.round(speed[i].value) >= Math.round(maxspeed)) {
            var maxspeed = speed[i].value;
        }
    }

    var speedAxis = new Array();
    for (var i = 0; i <= numYLabels; i++) {
        speedAxis.push({
            "count": i,
            "posx": offset.left - axisSpacing.x,
            "posy": (elem.height-offset.bottom-offset.top) - (elem.height-offset.bottom-offset.top) * i/numYLabels + offset.top,
            "value": Math.round(maxspeed * i/numYLabels)
        });
    }

    var interval = interval / 1000;
    var timeAxis = new Array();
    for (var i = 0; i <= numXLabels; i++) {
        timeAxis.push({
            "count": i,
            "posx": intervalWidth * i + offset.left,
            "posy": elem.height - offset.bottom + axisSpacing.y,
            "value": interval * i
        });
    }

    context.clearRect(0, 0, elem.width, elem.height);

    if(context) {
        // draw graph background
        context.shadowColor = "#777";
        context.shadowBlur = 0;
        context.fillStyle = "#eee";
        context.fillRect(offset.left, offset.top, graph.width, graph.height);

        // draw axis
        context.fillStyle = "#000";
        context.strokeStyle = "#fff";
        context.lineWidth = 2;

        context.shadowBlur = 3;
        context.beginPath();
        context.moveTo(offset.left, offset.top);
        context.lineTo(offset.left, elem.height - offset.bottom);
        context.lineTo(elem.width - offset.right, elem.height - offset.bottom);
        context.stroke();

        // draw axis labels
        context.shadowBlur = 0;
        $.each(speedAxis, function(i, value) {
            context.save();

            context.beginPath();
            context.moveTo(offset.left - 3, value.posy);
            context.lineTo(elem.width - offset.right, value.posy);
            context.stroke();

            if(maxspeed != 0 || value.count == 0) {
                context.shadowBlur = 0;
                context.textBaseline = "middle";
                context.textAlign = "end";
                context.fillText(value.value, value.posx, value.posy);
            }

            context.restore();
        });
        $.each(timeAxis, function(i, value) {
            context.save();

            context.beginPath();
            context.moveTo(value.posx, elem.height - offset.bottom);
            context.lineTo(value.posx, elem.height - offset.bottom + 3);
            context.stroke();

            context.textBaseline = "top";
            context.textAlign = "center";
            context.fillText(value.value, value.posx, value.posy);

            context.restore();
        });

        // draw graph
        context.fillStyle = "#219727";
        context.shadowBlur = 3;

        var speedData = new Array();
        for (var i = 0; i <= numXLabels; i++) {
            speedData.push({
                "count": i,
                "posx": offset.left + i*intervalWidth,
                "posy": (graph.height + offset.top) - (speed[i].value / maxspeed) * graph.height
            });
        }

        context.beginPath();
        context.moveTo(offset.left, elem.height - offset.bottom);
        $.each(speedData, function(i, value) {
            context.lineTo(value.posx, value.posy);
        });
        context.lineTo(offset.left + graph.width, offset.top + graph.height);
        context.lineTo(offset.left, offset.top + graph.height);
        context.fill();
        context.stroke();
    }
}

function formatUptime(sec) {
    sec = parseInt(sec, 10) || 0;
    if (sec <= 0) return "—";
    var d = Math.floor(sec / 86400);
    var h = Math.floor((sec % 86400) / 3600);
    var m = Math.floor((sec % 3600) / 60);
    if (d > 0) return d + "d " + h + "h";
    if (h > 0) return h + "h " + m + "m";
    return m + "m";
}

function updateSabPanel(start,limit) {
    var baseURL = sabBaseURL();
    var url = baseURL+'&action=getstatus';

    $("div.sabnzbdPanel .sw-panel-error").hide().empty();

    $.getJSON(url, function(json){
        if (!json || !json.queue) {
            $("div.sabnzbdPanel .sw-panel-error").show().text("No queue data from download client");
            return;
        }
        var queue = json.queue;
        var handlerType = $("div.sabnzbdPanel").data("handler") || "";

        if (queue.handler || queue.version) {
            var ver = queue.version ? ("v" + queue.version) : "";
            $("table.sabInfo td.handlername .handlerversion").text(ver);
        }
        if (typeof queue.post_job_count !== "undefined") {
            $("table.sabInfo td.postjobs").html("<strong>"+queue.post_job_count+"</strong>");
        }
        if (typeof queue.uptime !== "undefined") {
            $("table.sabInfo td.uptime").html("<strong>"+formatUptime(queue.uptime)+"</strong>");
        }
        // NZBGet-only extras; hide for SAB if empty
        if (handlerType === "nzbget" || queue.handler === "NZBGet") {
            $("table.sabInfo tr.sw-nzbget-extra").show();
        }

        if(queue.paused) {var state = "resume";} else {var state = "pause";}
        $("table.sabInfo td.state").html("<strong>"+queue.status+"</strong> (<a class='state' title='"+state+"'>"+state+"</a>)");
        $("table.sabInfo td.state a.state").click(function(){
            if(timeOut) {clearTimeout(timeOut)}
            sabActions(start,limit,state+"queue");
        });
        var freeD = (queue.freediskspace === "-" || queue.freediskspace === null || typeof queue.freediskspace === "undefined") ? "—" : queue.freediskspace;
        var totalD = (queue.totaldiskspace === "-" || queue.totaldiskspace === null || typeof queue.totaldiskspace === "undefined") ? "—" : queue.totaldiskspace;
        $("table.sabInfo td.diskspace").html("<strong title='<t>Free space (complete)</t>'>"+freeD+"</strong> / <strong title='<t>Totale space (complete)</t>'>"+totalD+"</strong> <t>GB</t>");
        $("table.sabInfo td.speed").html("<strong>"+((queue.bytepersec||0)/1048576).toFixed(2)+"</strong> <t>MB/s</t>");
        // NZBGet speedlimit is KB/s; SAB historically used % — label accordingly
        var limitLabel = (handlerType === "nzbget" || queue.handler === "NZBGet") ? "KB/s" : "%";
        $("table.sabInfo td.speedlimit").html("<input type='text' name='speedLimit' value='"+(queue.speedlimit!=0?queue.speedlimit:"")+"'><label>"+limitLabel+"</label>");
        $("td.speedlimit input[name=speedLimit]").focus(function(){
            $(this).addClass("hasFocus");
        });
        $("td.speedlimit input[name=speedLimit]").keyup(function(e) {
            if(e.keyCode == 13) {
                if(timeOut) {clearTimeout(timeOut)}
                sabActions(start,limit,'speedlimit');
            }
        });
        $("td.speedlimit input[name=speedLimit]").blur(function(){
            if(timeOut) {clearTimeout(timeOut)}
            sabActions(start,limit,'speedlimit');
        });

        var hours = Math.floor(queue.secondsremaining / 3600);
        var minutes = pad_zeros(Math.floor((queue.secondsremaining - (hours * 3600)) / 60),2);
        var seconds = pad_zeros((queue.secondsremaining % 60),2);

        $("table.sabInfo td.timeleft").html("<strong>"+hours+":"+minutes+":"+seconds+"</strong>");

        var eta = "-";
        if (queue.secondsremaining != 0)
        {
            var estimate = new Date();
            estimate.setSeconds(estimate.getSeconds() + queue.secondsremaining);
            eta = estimate.toLocaleString();
        }

        $("table.sabInfo td.eta").html("<strong>"+eta+"</strong>");
        $("table.sabInfo td.mb").html("<strong>"+queue.mbremaining+"</strong> / <strong>"+queue.mbsize+"</strong> <t>MB</t>");

        // Push a compact summary into the dashboard strip when present
        try {
            if (window.spotwebPowerUxApi && typeof window.spotwebPowerUxApi.updateDownloadLive === "function") {
                window.spotwebPowerUxApi.updateDownloadLive(queue);
            } else {
                var $live = $("[data-sw-dl-live]");
                if ($live.length) {
                    $live.find(".sw-dl-status").text(queue.status || "—").removeClass("sw-dash-ok sw-dash-warn sw-dash-fail")
                        .addClass(queue.paused ? "sw-dash-warn" : (queue.status === "Active" ? "sw-dash-ok" : ""));
                    $live.find(".sw-dl-speed").text(((queue.bytepersec||0)/1048576).toFixed(2) + " MB/s");
                    $live.find(".sw-dl-queue").text((queue.nrofdownloads||0) + " in queue");
                    if (freeD !== "—") {
                        $live.find(".sw-dl-disk").text(freeD + " GB free");
                    }
                }
            }
        } catch (e) {}

        // make sure we don't try to show more items than available in the queue
        while (start > queue.nrofdownloads)	{start -= limit;}
        // a start value lower than one is invalid
        if (start < 1) {start = 1;}

        var end = start+limit-1;

        $("table.sabQueue").empty();
        if(queue.nrofdownloads == 0) {
            $("table.sabQueue").html("<tr><td class='info'><t>No items in queue</t></td></tr>");
        } else {
            var index = 0;
            $.each(queue.slots, function(){
                var slot = this;

                index++;
                if ((index >= start) && (index <= end))
                {
                    if(slot.percentage == 0) {var progress = " empty"} else {var progress = "";}

                    $("table.sabQueue").append("<tr class='title "+index+"'><td><span class='move'><a class='up' title='<t>Up</t>'></a><a class='down' title='<t>Down</t>'></a></span><span class='delete'><a title='<t>Delete from queue</t>'></a></span><strong>"+index+".</strong><span class='title'>"+slot.filename+"</span></td></tr>");
                    $("table.sabQueue").append("<tr class='progressBar'><td><div class='progressBar"+progress+"' title='"+slot.mbremaining+" / "+slot.mbsize+" MB' style='width:"+slot.percentage+"%'></div></td></tr>");

                    $("table.sabQueue tr."+index+" a.up").click(function(){
                        if(timeOut) {clearTimeout(timeOut)}
                        sabActions(start,limit,'up', slot.id);
                    });
                    $("table.sabQueue tr."+index+" a.down").click(function(){
                        if(timeOut) {clearTimeout(timeOut)}
                        sabActions(start,limit,'down', slot.id);
                    });
                    $("table.sabQueue tr."+index+" span.delete a").click(function(){
                        if(timeOut) {clearTimeout(timeOut)}
                        if(start+1 > queue.nrofdownloads-1) {
                            sabActions(start-(limit-start),limit-(limit-start),'delete', slot.id);
                        } else {
                            sabActions(start,limit,'delete', slot.id);
                        }
                    });
                }
            });
        }

        if(queue.nrofdownloads != 0 && queue.nrofdownloads > end) {
            $("table.sabQueue").append("<tr class='nav'><td><t>Show %1 till %2 from a total of %3 results</t></td></tr>".replace('%1', start).replace('%2', end).replace('%3', queue.nrofdownloads));
        } else if(queue.nrofdownloads != 0 && end > queue.nrofdownloads) {
            if(queue.nrofdownloads == 1) {
                $("table.sabQueue").append("<tr class='nav'><td><t>Show 1 result</t></td></tr>");
            } else {
                $("table.sabQueue").append("<tr class='nav'><td><t>Show %1 till %2 from a total of %3 results</t></td></tr>".replace('%1', start).replace('%2', queue.nrofdownloads).replace('%3', queue.nrofdownloads));
            }
        } else if(queue.nrofdownloads != 0 && end == queue.nrofdownloads) {
            $("table.sabQueue").append("<tr class='nav'><td><t>Show %1 till %2 from a total of %3 results</t></td></tr>".replace('%1', start).replace('%2', end).replace('%3', queue.nrofdownloads));
        }

        if(queue.nrofdownloads == 1) {
            $("table.sabQueue tr.title td span.move").hide();
        } else {
            if (start == 1){
                $("table.sabQueue tr.title td span.move").first().css('padding', '2px 4px 3px 0').children("a.up").hide();
            }
            if (end >= queue.nrofdownloads){
                $("table.sabQueue tr.title td span.move").last().css('padding', '2px 4px 3px 0').children("a.down").hide();
            }
        }

        if(start > 1) {
            $("table.sabQueue tr.nav td").prepend("<a class='prev' title='<t>Previous</t>'>&lt;&lt;</a> ");
        }
        if(queue.nrofdownloads > end) {
            $("table.sabQueue tr.nav td").append(" <a class='next' title='<t>Next</t>'>&gt;&gt;</a>");
        }

        $("table.sabQueue tr.nav a").click(function(){
            if(timeOut) {clearTimeout(timeOut)}
            if($(this).hasClass("prev")) {
                updateSabPanel(start-limit,limit);
            } else if($(this).hasClass("next")) {
                updateSabPanel(start+limit,limit);
            }
        });

        $("tr.title td span.title").mouseenter(function(){
            $(this).addClass("hover");
        }).mouseleave(function(){
                if($(this).hasClass("hover")) {
                    if(timeOut) {clearTimeout(timeOut)}
                    $(this).removeClass("hover");
                    updateSabPanel(start,limit);
                }
            });

        var interval = 5000;
        drawGraph(queue.bytepersec/1024, interval);

        var timeOut = setTimeout(function(){
            if($("div.sabnzbdPanel").is(":visible") && !($("td.speedlimit input[name=speedLimit]").hasClass("hasFocus")) && !($("tr.title td span.title").hasClass("hover"))) {
                updateSabPanel(start,limit);
            }
        }, interval);
    }).fail(function(xhr) {
        var msg = "Could not reach download client";
        if (xhr && xhr.responseText) {
            msg = xhr.responseText.replace(/<[^>]+>/g, " ").replace(/\s+/g, " ").trim().substring(0, 200) || msg;
        }
        $("div.sabnzbdPanel .sw-panel-error").show().text(msg);
        $("table.sabInfo td.state").html("<strong class='sw-dash-fail'>Offline</strong>");
        var $live = $("[data-sw-dl-live]");
        if ($live.length) {
            $live.find(".sw-dl-status").text("Offline").removeClass("sw-dash-ok sw-dash-warn").addClass("sw-dash-fail");
            $live.find(".sw-dl-speed,.sw-dl-queue,.sw-dl-disk").text("");
        }
        // Retry while panel is open
        setTimeout(function(){
            if($("div.sabnzbdPanel").is(":visible")) {
                updateSabPanel(start,limit);
            }
        }, 8000);
    });
}
