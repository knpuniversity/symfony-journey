kernel.request and the RouterListener
-------------------------------------

So let's start working through this. The first thing we have is this
``$this->requestStack->push()`` This is going to make more sense a few lessons
from now. But as we saw before, Symfony has the ability to process many requests
at a time via something called sub requests. The requestStack is just an object
that keeps track of which request is being worked on right now. We'll talk
more about that idea later - for now, just ignore it.

For us, the first interesting that happens inside of this function is that
it creates a ``GetResponseEvent`` object. Despite its name, that doesn't do
anything, that just creates a new object with some data in it. Some of you
may already be saying: "Hold on, that class sounds familiar." If we look
inside our UserAgentSubscriber, you'll remember that it was passed a ``GetResponseEvent``
object. And this is where that object was created. It then goes to a dispatcher
and dispatches our first event. That ``KernelEvents::REQUEST`` is just a
constant that means ``kernel.request``. So ultimately, this line causes
the dispatcher to loop over and call *all* of the functions that are "listening"
to the ``kernel.request`` event. 

And because of this nice constant, we can actually use it inside our subscriber.
So I'll change this to ``KernelEvents::REQUEST`` and add a comment so I don't
forget what this really means. So when every listener like this is called,
it's passed that ``GetResponseEvent`` that's created in ``HttpKernel``. 

There are a lot of listener to ``kernel.request``, but there's only one
that's mission critical to how the Symfony Framework works. Let's go back
to the browser, refresh the page, and click into the profiler. Back on the
events tab, we can see all of the functions that are called when this event
is dispatched. The one that's important to understand how the framework works
is that ``RouterListener`` one that we can see in the middle. So let's go
back and open up ``RouterListener`` in PhpStorm. If you ever see a class
in the ``app/cache`` directory like this, ignore that, you want the one
that's really inside of the ``vendor/`` directory. 

If you look at the bottom, this is an event subscriber, just like our event
subscriber. And you can see it listens on ``kernel.request`` and its ``onKernelRequest``
method is called. As the name of this class sounds, this is the class that's
responsible for actually executing your routing. And remember, we can write
routing in a lot of different ways: in YAML files, in annotations like in
our app or other formats. But ultimately, if you run ``router:debug``, you'll
get a list of all of the routes. Most of these are internal debugging routes,
but our 2 are at the bottom.

My question is, what's the end result of router? For example, if we're on
the homepage, does it return the string ``dinosaur_list``? Something else?
If we trace down in the function, between lines 124 and 128, that's where
the router is actually being run. You can see there's an if statement, but
that's not important - both sides do the same thing: they figure out which
route matched.

And you can see that it returns some ``$parameters`` variable. What's in
that? Is that the name of the route or something else? Let's use the ``dump()``
function to find out. This runs on every request, so let's go back, refresh,
and there it is. When the router matches a route, what it returns is an array
of information about that route. And the most important piece of information
is what controller to execute, which it puts on an ``_controller`` key.
Because remember, our flow is always request, routing, routing points to a
controller, and the controller is where we return the Response.

The format of the controller is the class name, ``::`` then the method name.
If you open up the ``DinosaurController``, this all comes from here. When
you use annotation routing, it's smart enough to create a route where the
``_controller`` is set to whatever method is below it.

If you've used YAML routes before, it's even more interesting. I'll open
up my ``app/config/routing.yml`` file. At the bottom in comments, I prepared
a route that's identical to the one we're building in annotations. When you
use YAML routing, to point to the controller you have a ``defaults`` key,
and below that you have an ``_controller`` that uses a three-part syntax
to point to the ``DinosaurController`` and ``indexAction``.

So just to prove this is the same. I'm going to load *only* that route, and
when we refresh, the routing parameters are exactly the same. Let's comment
that back out.

Now let's go to a different page - ``/dinosaurs/22``. And what we see *now*
is that in addition to ``_controller`` and ``_route``, now we have the ``id``
value from the route. This shows that the end result of the routing layer
is an array that has the ``_controller`` key plus any wildcards that are
in your URL. It also gives you the ``_route`` in case you need it, but that's
not important.

One more cool thing. If you look in the cache directory, you'll see a file
called ``appDevUrlMatcher.php``. This is the end result of parsing through
all of your routes, whether they're written in annotations, YAML or some
other format. So when we see ``$this->matcher->match`` in ``RouterListener``,
that's actually calling the ``match`` function you see inside of that cached
class. Symfony is smart enough to parse all of our routes, then generate this
big crazy, regex, if-statement matching algorithm. If we scroll to the bottom,
you'll see our dinosaur pages. Ok, this isn't important to understand, I
just think it's cool.

So let's get rid of the ``dump()`` and trace through what happens next.
If you look below, you'll see that it takes that ``$parameters`` array, which
has ``_controller`` and ``id``, and it puts onto a ``$request->attributes``
property. Symfony's request object has a bunch of these public properties.
I'll open up the Components documentation for the HttpFoundation component,
because it talks about this.

Every public property except for one, has a real-world equivalent. For example,
if you want to get the query parameters, you say ``$request->query->get``.
If you want to get the cookies, it's ``$request->cookies->get``. For the
headers, it's ``$request->header->get``. All of these are ways to get information
that comes from the original HTTP request message.

The one weird guy is ``$request-attributes``. It has no real-world equivalent.
It's just a place for you to store application-specific information about
the request. And route info is exactly that.

Putting information onto the ``$request->attributes`` property doesn't actually
do anything. It's just a place to store data - it's not triggering any other
systems. We're just modifying the request object, and that's it for the
RouterListener.

Let's close this up and go back to ``HttpKernel``. At this point, the *only*
thing we've done is dispatch the ``kernel.request`` event and the only listener
that's really important is the ``RouterListener``. And all it did was modify
the ``$request->attributes``. So not a lot has happened yet. 

------------

If we follow this down, there's a really interesting ``if`` statement. If
the event has a response - and I'll show you what that means - it exits
immediately before calling the controller or doing anything else. We'll
look at that ``filterControllerResponse`` method later, but it doesn't do
anything mission critical. 

This means that any listener to ``kernel.request`` can just create a ``Response``
object and say "I'm done". For example, if you had a maintenance mode, you
could create a listener, check some flag to see if you're in maintenance
mode, and return a response immediately that says: "We're fixing some things."

Let's try this. In ``UserAgentSubscriber``, I'll create a new Response object,
make sure you use the one from the ``HttpFoundation`` component. PHPStorm
did just add a ``use`` statement for me up on line 7. And we'll say "Come back later".
And the ``GetResponseEvent`` object we're passed has an ``$event->setResponse()``
method on it. Remember, every event passes a different object, and this one
happens to have this setResponse method on it. We'll make things more interesting
and put this in a block so it only randomly sets the response.

If we go and refresh now, the page works fine, refresh again... mine is
being stubborn. There we go. You can see that as soon the response is set,
it just stops entirely.

I'll leave this code in there, but let's comment it out so it doesn't ruin
our project. Perfect!

In reality, there's no listener that's setting the response for us. So our
next job will be to figure out which controller function to call to create
the Response.
