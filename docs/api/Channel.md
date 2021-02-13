###### PHEXT > [Detach](../README.md) > [API Reference](index.md) > Channel
------
### Channel
Channel is a loose implentation of channels from the Go language. It provides a simple way of allowing independant processes to send and receive data between one another.

A channel is a block-in, and (by default) a block-out mechanism, meaning that the task that sets a value will block until another task has received it.
#### Methods
[__construct](#__construct)
[close](#close)
[set](#set)
[put](#put)
[get](#get)
[next](#next)

------
##### __construct
```php
public function __construct() 
```
Construct a new Channel.


------
##### close
```php
public function close() : sqonk\phext\detach\Channel
```
Close off the channel, signalling to the receiver that no further values will be sent.


------
##### set
```php
public function set($value) : sqonk\phext\detach\Channel
```
Pass a value into the channel. This method will block until the channel is free to receive new data again.


------
##### put
```php
public function put($value) : sqonk\phext\detach\Channel
```
Alias for Channel::set().


------
##### get
```php
public function get($wait = true) 
```
Obtain the next value on the channel (if any). If $wait is `TRUE` then this method will block until a new value is received. Be aware that in this mode the method will block forever if no further values are sent from other tasks.

If $wait is given as an integer of 1 or more then it is used as a timeout in seconds. In such a case, if nothing is received before the timeout then a value of `NULL` will be returned.

$wait defaults to `TRUE`.


------
##### next
```php
public function next($wait = true) 
```
Alias for Channel::get().


------
