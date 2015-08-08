Observer.subscribe('deposit_offers', function(){
    $('#publicInfoContainer').load('{% url get_public_info %}');
})
 
Observer.signal('deposit_offers')
 
var Observer = {
    _subscribers: {},
    subscribe: function(key, callback) {
        if (!(key in this._subscribers)) {
            this._subscribers[key]= []
        }
 
        this._subscribers[key].push(callback)
    },
 
    signal: function(key, params) {
        if (key in this._subscribers) {
            for (item in this._subscribers[key]) {
                this._subscribers[key][item]();
            }
        }
    }
}
