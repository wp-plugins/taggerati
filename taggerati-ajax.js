function sendPOST(url, parameters) {
	var http_request = false;
	var debug = false;
	
	if (window.XMLHttpRequest) {
		http_request = new XMLHttpRequest();
	} else if (window.ActiveXObject) {
		try {
			http_request = new ActiveXObject("Msxml2.XMLHTTP");
		} catch (e) {
			try {
				http_request = new ActiveXObject("Microsoft.XMLHTTP");
			} catch (e) {}
		}
	}

	if (!http_request) {
		alert('Cannot create XMLHTTP instance');
		return false;
	}
	
	if(debug){
		http_request.onreadystatechange = function(){
			if(http_request.readyState == 4 && http_request.status == 200){
				alert(http_request.responseText);
			}
		}
	}
	
	http_request.open('POST', url, true);
	http_request.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
	http_request.setRequestHeader("Content-length", parameters.length);
	http_request.setRequestHeader("Connection", "close");
	http_request.send(parameters);
}
   
function addTag(url, tag, id, tagpage){
	// make an AJAX call
	sendPOST(url, "tag=" + encodeURI(tag) +"&ID=" + encodeURI(id) + "&action=addtag");
   		
	// add it to the list
	var taglist = document.getElementById("taggeratitaglist");
	taglist.innerHTML += ' <a href="' + tagpage + tag + '">' + tag + '</a> ';
}