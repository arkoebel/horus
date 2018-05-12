var horusTemplates = {};
jQuery(document).ready(function () {
	$.get('getHorusDestinations.php', null, getTemplates, 'json');
	$.get('endpoints.json', null, getEndpoints, 'json');
	$("#select").change(selectTemplate);
	$("#go").click(getOutput);
	$("#save").click(save);
	$("#restore").click(retrieve);
	$("#fileselect").change(getFileName);
	/*   $('a[href*="#templatelink"]').click(function(){
	$("#templateslist").addClass("hidden");
	$("#templatescontenttype").addClass("hidden");
	$("#querycontent").removeClass("hidden");
	$("#querytext").removeClass("hidden");
	});
	$('a[href*="#freelink"]').click(function(){
	$("#templateslist").removeClass("hidden");
	$("#templatescontenttype").removeClass("hidden");
	$("#querycontent").addClass("hidden");
	$("#querytext").addClass("hidden");
	});*/
});

function getTemplates(res) {
	horusTemplates = res;
	var option = '<option value="-1">Select</option>';
	$.each(res, function (i, d) {
		//option += '<option>'+d.name+'<\/option>';
		option += '<option value="' + i + '">' + i + '<\/option>';
	});
	$("#select").html(option);

};

function getEndpoints(res) {
	var optionProxy = '<option value="-1">None</option>';
	var optionDestination = '<option value="-1">Select</option>';
	$.each(res, function (i, d) {
		if (d.transformation === "true") {
			optionProxy += '<option value="' + d.url + '">' + d.name + '</option>';
		}
		if (d.endpoint === 'true') {
			optionDestination += '<option value="' + d.url + '" class="' + d.contenttype + '">' + d.name + '</option>';
		}
	});
	$("#selectDestination").html(optionDestination);
	$("#selectDestination").change(selectDestination);
	$("#selectProxy").html(optionProxy);
	$("#selectProxy").change(selectProxy);
};

function selectDestination() {
	var opt = $("#selectDestination option:selected").val();
	$("#destination").val(opt);
};

function selectProxy() {
	var opt = $("#selectProxy option:selected").val();
	$("#proxy").val(opt);
};

function selectTemplate() {
	var opt = $("#select option:selected").text();
	for (var i = 0; i < Object.keys(horusTemplates).length; i++) {
		if (Object.keys(horusTemplates)[i] === opt) {
			var table = '<table><tr><th>Variable</th><th>Value</th></tr>';
			var indx = (Object.entries(horusTemplates)[i])[1].params;
			$("#sourcetype").val((Object.entries(horusTemplates)[i])[1].type);
			for (var j = 0; j < indx.length; j++) {
				var indxj = indx[j];
				table += '<tr><td>' + indxj + '</td><td id="' + indxj + '"><input id="val_' + indxj + '"></input></td></tr>';
			}
			$("#tab").html(table);
		}
	}
}

function getOutput() {
	var output = {
		attr: {}
	};
	var data = "";
	
	if ($("#home-tab").hasClass("active")) {
    	output.template = $("#select option:selected").text();
		output.sourcetype = $("#sourcetype").val();
	
		output.repeat = $("#number").val();
		output.destinationcontent = $("#selectDestination option:selected").attr("class");
		$("#tab tr td input").each(function () {
			output.attr[$(this).attr("id").substring(4)] = $(this).val();
		});
		if ($("#proxy").val() !== ""){
			$.ajaxSetup({
				contentType: "application/json; charset=utf-8",
				headers: {
					'x_destination_url': $("#destination").val()
				}
			});
		}else{
			$.ajaxSetup({
				contentType: "application/json; charset=utf-8"
			});
		}
		data = JSON.stringify(output);
	}else{
		if ($("#proxy").val() !== ""){
			$.ajaxSetup({
				contentType: $("#freecontent").val(),
				processData: false,
				headers: {
					'x_destination_url': $("#destination").val()
				}
			});
		}else{
			$.ajaxSetup({
				contentType: $("#freecontent").val(),
				processData: false
			});
		}
		data = $("#freetext").val();
	}
	if ($("#proxy").val() !== "") {
		$.post($("#proxy").val(), data, function (res) {
			alert("OK")
		}, "json");
	} else if ($("#destination").val() != "") {
		$.post($("#destination").val(), data, function (res) {
			alert("OK")
		}, "json");
	} else {
		alert("You must choose a destination");
	}
};

function save() {
	//$("#fileselect").trigger("click");
	//alert($("#fileselect").attr("value"));
	var output = {
		attr: {}
	};
	if($("#home-tab").hasClass("active")) {
		output.mode = "template";
		output.template = $("#select option:selected").text();
		output.repeat = $("#number").val();
		$("#tab tr td input").each(function () {
			output.attr[$(this).attr("id").substring(4)] = $(this).val();
		});
	}else{
		output.mode = "free";
		output.contenttype = $("#freecontent").val();
		output.data = $("#freetext").val();
	}
	output.proxy = $("#proxy").val();
	output.proxyname = $("#selectProxy option:selected").text();
	output.destination = $("#destination").val();
	output.destinationname = $("#selectDestination option:selected").text();
	saveAs(new Blob([(JSON.stringify(output))]), $("#saveas").val());

};

function retrieve() {
	$("#fileselect").trigger("click");
}

function getFileName() {
	var reader = new FileReader();
	reader.onload = (function (theFile) {
		return function (e) {
			$("#saveas").val($("#fileselect").get(0).files[0].name);
			var res = JSON.parse(e.target.result);
			if (res.mode=="template"){
				$("#home-tab").tab('show');
				$("#select").val(res.template);
				$("#number").val(res.repeat);
				$.each(res.attr, function (index, item) {
					$("#val_" + index).val(item);
				});
				selectTemplate();
			}else{
				$("#profile-tab").tab('show');
				$("#freecontent").val(res.contenttype);
				$("#freetext").val(res.data);
			}
			
			$("#selectProxy").val(res.proxy);
			$("#selectDestination").val(res.destination);
			
			$("#proxy").val(res.proxy);
			$("#destination").val(res.destination);
			
		};
	})($("#fileselect").get(0).files[0]);
	reader.readAsText($("#fileselect").get(0).files[0]);

};
