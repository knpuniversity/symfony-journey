Symfony Magic: Replace the _controller
======================================

If this were the Matrix, you'd be seeing 1's and 0's running across your
code. Your Symfony world is now one without rules or controls, without borders
or boundaries. Let's prove it.

We know that Symfony reads the ``_controller`` key in the request attributes
and executes that as the controller. This is set by the ``RouterListener``
but it could be set anywhere. 

Let's go adventuring!. ``UserAgentSubscriber`` listens to ``kernel.request``.
What if we just replaced the ``_controller`` key here with something else?
Let's do that, and set it to an anonymous function::

    // src/AppBundle/EventListener/UserAgentSubscriber.php
    // ...
    use Symfony\Component\HttpFoundation\Response;

    public function onKernelRequest(GetResponseEvent $event)
    {
        // ...

        $request->attributes->set('_controller', function() {
            return new Response('Hello Dinosaur!');
        });
    }

Symfony doesn't see any difference between this function and a traditional
controller. To prove it, return a normal ``Response``::

    return new Response('Hello world!') 

When we refresh, would you believe it? That actually works! Comment this line
out.

Forcing RouterListener NOT to Route
-----------------------------------

Even within listeners to a single event, there is an order of things. In
the events tab of the Profiler, you can see that our listener is the *last*
one that's called by ``kernel.request``. The ``RouterListener`` is executed
before us. That means that the routing *is* being run and the ``_controller``
key *is* being set. Later, our listener replaces it.

But even if our listener were run before the router, our little hack would
still work. That's because ``RouterListener`` has a special piece of code
near the top that checks to see if the ``_controller`` key is already set.
If it *is* set somehow, the routing never runs::

    // vendor/symfony/symfony/src/Symfony/Component/HttpKernel/EventListener/RouterListener.php
    // ...

    public function onKernelRequest(GetResponseEvent $event)
    {
        // ...

        if ($request->attributes->has('_controller')) {
            // routing is already done
            return;
        }

        // ...
    }

That'll be important when we talk about sub-requests.

And since our anonymous function is a valid controller, we can still use
arguments on it just like normal. So if I go to ``/dinosaurs/22``, which
has a ``{id}`` in the route, we're allowed to have an ``$id`` argument::

    // src/AppBundle/EventListener/UserAgentSubscriber.php
    // ...
    use Symfony\Component\HttpFoundation\Response;

    public function onKernelRequest(GetResponseEvent $event)
    {
        // ...

        $request->attributes->set('_controller', function($id) {
            return new Response('Hello '.$id);
        });
    }

So this will work just fine. All controllers are created equal.

Now, let's comment that out temporarily, or more likely permanently::


    // src/AppBundle/EventListener/UserAgentSubscriber.php
    // ...
    use Symfony\Component\HttpFoundation\Response;

    public function onKernelRequest(GetResponseEvent $event)
    {
        // ...

        /*
        $request->attributes->set('_controller', function($id) {
            return new Response('Hello '.$id);
        });
        */
    }
