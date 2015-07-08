// Change localhost to the name or ip address of the host running the chat server
var chatUrl = 'ws://localhost:9911';

function displayChatMessage(from, message) {
    var node = document.createElement("LI");

    if (from) {
        var nameNode = document.createElement("STRONG");
        var nameTextNode = document.createTextNode(from);
        nameNode.appendChild(nameTextNode);
        node.appendChild(nameNode);
    }

    var messageTextNode = document.createTextNode(message);
    node.appendChild(messageTextNode);

    document.getElementById("messageList").appendChild(node);
}

var conn;

function connectToChat() {
    conn = new WebSocket(chatUrl);

    conn.onopen = function() {
        document.getElementById('connectFormDialog').style.display = 'none';
        document.getElementById('messageDialog').style.display = 'block';

        var params = {
            'roomId': document.getElementsByName("room.name")[0].value,
            'userName': document.getElementsByName("user.name")[0].value,
            'action': 'connect'
        };
        console.log(params);
        conn.send(JSON.stringify(params));
    };

    conn.onmessage = function(e) {
        console.log(e);
        var data = JSON.parse(e.data);

        if (data.hasOwnProperty('message') && data.hasOwnProperty('from')) {
            displayChatMessage(data.from.name, data.message);
        }
        else if (data.hasOwnProperty('message')) {
            displayChatMessage(null, data.message);
        }
        else if (data.hasOwnProperty('type') && data.type == 'list-users' && data.hasOwnProperty('clients')) {
            displayChatMessage(null, 'There are '+data.clients.length+' users connected');
        }
    };

    conn.onerror = function(e) {
        console.log(e);
    };

    return false;
}

function sendChatMessage() {
    var d = new Date();
    var params = {
        'message': document.getElementsByName("message")[0].value,
        'roomId': document.getElementsByName("room.name")[0].value,
        'userName': document.getElementsByName("user.name")[0].value,
        'action': 'message',
        'timestamp': d.getTime()/1000
    };
    conn.send(JSON.stringify(params));

    document.getElementsByName("message")[0].value = '';
    return false;
}