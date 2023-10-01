###### PHEXT > [Detach](../README.md) > [API Reference](index.md) > BufferedChannel
------
### BufferedChannel
A BufferedChannel is an queue of values that may be passed between tasks. Unlike a standard channel, it may continue to accept new values before any existing ones have been read in via another task.

The queue is unordered, meaning that values may be read in in a different order from that of which they were put in.

BufferedChannels are an effective bottle-necking system where data obtained from multiple tasks may need to be fed into a singular thread for post-processing.

@implements \IteratorAggregate<int, BufferedChannel>
#### Methods
- [__construct](#__construct)
- [__destruct](#__destruct)
- [capacity](#capacity)
- [close](#close)
- [is_open](#is_open)
- [set](#set)
- [put](#put)
- [bulk_set](#bulk_set)
- [get](#get)
- [next](#next)
- [get_all](#get_all)
- [incoming](#incoming)
- [getIterator](#getiterator)

------
##### __construct
```php
public function __construct(int $capacity = 0) 
```
Construct a new BufferedChannel.


------
##### __destruct
```php
public function __destruct() 
```
No documentation available.


------
##### capacity
```php
public function capacity(int $totalDeposits) : sqonk\phext\detach\BufferedChannel
```
Set an arbitrary limit on the number of times data will be read from the channel. Once the limit has been reached the channel will be closed.

Every time this method is called it will reset the write count to 0.


------
##### close
```php
public function close() : self
```
Close off the channel, signalling to the receiver that no further values will be sent.


------
##### is_open
```php
public function is_open() : bool
```
Is the channel still open?

**Returns:**  bool `TRUE` if the channel is open, `FALSE` if not.


------
##### set
```php
public function set(mixed $value) : self
```
Queue a value onto the channel, causing all readers to wake up.


------
##### put
```php
public function put(mixed $value) : self
```
Alias for Channel::set().


------
##### bulk_set
```php
public function bulk_set(array $values) : self
```
Queue a bulk set of values onto the channel, causing all readers to wake up.

If you have a large number of items to push onto the queue at once then this method will be faster than calling set() for every element in the array.

- **array<mixed>** $values The dataset to store.


------
##### get
```php
public function get(int|bool $wait = true) : mixed
```
Obtain the next value on the queue (if any). If $wait is `TRUE` then this method will block until a new value is received. Be aware that in this mode the method will block forever if no further values are queued from other tasks.

If $wait is given as an integer of 1 or more then it is used as a timeout in seconds. In such a case, if nothing is received before the timeout then a value of `NULL` will be returned if nothing is received prior to the expiry.

--parameters: @param int|bool $wait If `TRUE` then block indefinitely until a new value is available. If `FALSE` then return immediately if there is nothing available. If a number is passed then wait the given number of seconds before giving up. Passing 0 is equivalent to passing `TRUE`. Passing a negative number will throw an exception.

**Returns:**  mixed The next available value, `NULL` if none was available or a wait timeout was reached. If the channel was closed then the constant CHAN_CLOSED is returned.


------
##### next
```php
public function next(int|bool $wait = true) : mixed
```
Alias for Channel::get().


------
##### get_all
```php
public function get_all(int|bool $wait = true) : array|string|null
```
Obtain all values currently residing on the queue (if any). If $wait is `TRUE` then this method will block until a new value is received. Be aware that in this mode the method will block forever if no further values are queued from other tasks.

If $wait is given as an integer of 1 or more then it is used as a timeout in seconds. In such a case, if nothing is received before the timeout then a value of `NULL` will be returned.

--parameters: @param int|bool $wait If `TRUE` then block indefinitely until a new value is available. If `FALSE` then return immediately if there is nothing available. If a number is passed then wait the given number of seconds before giving up. Passing 0 is equivalent to passing `TRUE`. Passing a negative number will throw an exception.

**Returns:**  array<mixed> The next available value, `NULL` if none was available or a wait timeout was reached. If the channel was closed then the constant CHAN_CLOSED is returned.


------
##### incoming
```php
public function incoming(int|bool $wait = true) : Generator
```
Yield the channel out to an iterator loop until the point at which it is closed off. If you wish to put your task into an infinite scanning loop for the lifetime of the channel, for example to process all incoming data, then this can provide a more simplistic model for doing so.

- **int|bool** $wait If `TRUE` then block indefinitely until a new value is available. If `FALSE` then return immediately if there is nothing available. If a number is passed then wait the given number of seconds before giving up. Passing 0 is equivalent to passing `TRUE`. Passing a negative number will throw an exception.


------
##### getIterator
```php
public function getIterator() : Traversable
```
Use the channel object as an iterator for incoming values, looping until it is closed off. This method has the same effect as calling BufferedChannel::incoming() with the default parameter of `TRUE` for the $wait parameter.


------
