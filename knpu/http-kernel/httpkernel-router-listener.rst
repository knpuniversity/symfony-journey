kernel.request and the RouterListener
-------------------------------------

So let's start working through this. The first thing we have is this
``$this->requestStack->push()``::

    // vendor/symfony/symfony/src/Symfony/Component/HttpKernel/HttpKernel.php
    // ...

    private function handleRaw(Request $request, $type = self::MASTER_REQUEST)
    {
        $this->requestStack->push($request);
        // ...
    }

This is going to make more sense a few lessons from now. But as we saw before,
Symfony has the ability to process many requests at a time via something
called :doc:`sub-requests`. The ``$requestStack`` is just an object that
keeps track of which request is being worked on right now. We'll talk more
about that idea later - for now, just ignore it.

The kernel.request Event
------------------------

For us, the first interesting thing that happens inside of this function is that
it creates a ``GetResponseEvent`` object::

    // vendor/symfony/symfony/src/Symfony/Component/HttpKernel/HttpKernel.php
    // ...

    private function handleRaw(Request $request, $type = self::MASTER_REQUEST)
    {
        // ...
        
        // request
        $event = new GetResponseEvent($this, $request, $type);

        // ...
    }

Despite its name, that doesn't do anything, that just creates a new object
with some data in it. Some of you may already be saying: "Hold on, that class
sounds familiar." If we look inside our ``UserAgentSubscriber``, you'll remember
that it was passed a ``GetResponseEvent`` object::

    // src/AppBundle/EventListener/UserAgentSubscriber.php
    // ...

    public function onKernelRequest(GetResponseEvent $event)
    {
        // ...
    }

And this is where that object was created. It then goes to a dispatcher
and dispatches our first event::

    // vendor/symfony/symfony/src/Symfony/Component/HttpKernel/HttpKernel.php
    // ...

    private function handleRaw(Request $request, $type = self::MASTER_REQUEST)
    {
        // ...
        
        // request
        $event = new GetResponseEvent($this, $request, $type);
        $this->dispatcher->dispatch(KernelEvents::REQUEST, $event);

        // ...
    }

That ``KernelEvents::REQUEST`` is just a constant that means ``kernel.request``.
So ultimately, this line causes the dispatcher to loop over and call *all*
of the functions that are "listening" to the ``kernel.request`` event. 

And because of this nice constant, we can actually use it inside our subscriber.
So I'll change this to ``KernelEvents::REQUEST`` and add a comment so I don't
forget what this really means::

    // src/AppBundle/EventListener/UserAgentSubscriber.php
    // ...

    use Symfony\Component\HttpKernel\KernelEvents;

    class UserAgentSubscriber implements EventSubscriberInterface
    {
        public static function getSubscribedEvents()
        {
            return array(
                // constant that means kernel.request
                KernelEvents::REQUEST => 'onKernelRequest'
            );
        }
    }

So when every listener like to this is called, it's passed that ``GetResponseEvent``
that's created in ``HttpKernel``. 

King of Routing: RouterListener
-------------------------------

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
subscriber::

    // vendor/symfony/symfony/src/Symfony/Component/HttpKernel/EventListener/RouterListener.php
    // ...

    class RouterListener implements EventSubscriberInterface
    {
        // ...

        public static function getSubscribedEvents()
        {
            return array(
                KernelEvents::REQUEST => array(array('onKernelRequest', 32)),
                KernelEvents::FINISH_REQUEST => array(array('onKernelFinishRequest', 0)),
            );
        }
    }

And you can see it listens on ``kernel.request`` and its ``onKernelRequest``
method is called. As the name of this class sounds, this is the class that's
responsible for actually executing your routing. And remember, we can write
routing in a lot of different ways: in YAML files, in annotations like in
our app or other formats. But ultimately, if you run ``router:debug``, you'll
get a list of all of the routes:

.. code-block:: bash

    php app/console router:debug

Most of these are internal debugging routes, but our 2 are at the bottom:

.. code-block:: text

    Name                      Path                              
    _wdt                      /_wdt/{token}
    ...

    dinosaur_list             /
    dinosaur_show             /dinosaurs/{id}

What does Routing Do?
~~~~~~~~~~~~~~~~~~~~~

My question is, what's the end result of router? For example, if we're on
the homepage, does it return the string ``dinosaur_list``? Something else?
If we trace down in the function, between lines 124 and 128, that's where
the router is actually being run::

    // vendor/symfony/symfony/src/Symfony/Component/HttpKernel/EventListener/RouterListener.php
    // ...

    public function onKernelRequest(GetResponseEvent $event)
    {
        // ...

        if ($this->matcher instanceof RequestMatcherInterface) {
            $parameters = $this->matcher->matchRequest($request);
        } else {
            $parameters = $this->matcher->match($request->getPathInfo());
        }
        
        // ...
    }

You can see there's an if statement, but that's not important - both sides
do the same thing: they figure out which route matched.

Routing Parameters
~~~~~~~~~~~~~~~~~~

And you can see that it returns some ``$parameters`` variable. What's in
that? Is that the name of the route or something else? Let's use the ``dump()``
function to find out::

    if ($this->matcher instanceof RequestMatcherInterface) {
        $parameters = $this->matcher->matchRequest($request);
    } else {
        $parameters = $this->matcher->match($request->getPathInfo());
    }
    
    dump($parameters);die;

This runs on every request, so let's go back, refresh, and there it is. When
the router matches a route, what it returns is an array of information about
that route:

.. code-block:: text

    array(
        '_controller' => 'AppBundle\Controller\DinosaurController::indexAction',
        '_route' => 'dinosaur_list',
    )

And the most important piece of information is what controller to execute,
which it puts on an ``_controller`` key. Because remember, our flow is always
request, routing, routing points to a controller, and the controller is where
we return the ``Response``.

The format of the controller is the class name, ``::`` then the method name.
If you open up the ``DinosaurController``, this all comes from here::

    // src/AppBundle/Controller/DinosaurController.php
    // ...
    
    /**
     * @Route("/", name="dinosaur_list")
     */
    public function indexAction()
    {
        // ...
    }

When you use annotation routing, it's smart enough to create a route where
the ``_controller`` is set to whatever method is below it.

The _controller key and Yaml Routes
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

If you've used YAML routes before, it's even more interesting. I'll open
up my ``app/config/routing.yml`` file. At the bottom in comments, I prepared
a route that's identical to the one we're building in annotations:

.. code-block:: yaml

    # app/config/routing.yml
    # ...

    #dinosaur_list:
    #    path: /
    #    defaults:
    #        _controller: AppBundle:Dinosaur:index


When you use YAML routing, to point to the controller you have a ``defaults``
key, and below that you have an ``_controller`` that uses a three-part syntax
to point to the ``DinosaurController`` and ``indexAction``.

So just to prove this is the same. I'm going to load *only* that route:

.. code-block:: yaml

    # app/config/routing.yml
    # delete the top annotations import temporarily

    dinosaur_list:
        path: /
        defaults:
            _controller: AppBundle:Dinosaur:index

And when we refresh, the routing parameters are exactly the same. Let's comment
that back out:

.. code-block:: yaml

    # app/config/routing.yml
    _app_bundle_annotations:
        resource: @AppBundle/Controller
        type:     annotation

    #dinosaur_list:
    #    path: /
    #    defaults:
    #        _controller: AppBundle:Dinosaur:index

Routing Wildcards are Parameters Too!
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

Now let's go to a different page - ``/dinosaurs/22``. And what we see *now*
is that in addition to ``_controller`` and ``_route``, now we have the ``id``
value from the route:

.. code-block:: text

    array(
        '_controller' => 'AppBundle\Controller\DinosaurController::showAction',
        'id' => '22',
        '_route' => 'dinosaur_show',
    )

This shows that the end result of the routing layer is an array that has
the ``_controller`` key plus any wildcards that are in your URL. It also
gives you the ``_route`` in case you need it, but that's not important.

The Dumped Router (it's cool!)
------------------------------

One more cool thing. If you look in the cache directory, you'll see a file
called ``appDevUrlMatcher.php``::

    // app/cache/dev/appDevUrlMatcher.php
    // ...

    class appDevUrlMatcher extends Symfony\Bundle\FrameworkBundle\Routing\RedirectableUrlMatcher
    {
        // ...
        public function match($pathinfo)
        {
            // ...

            // dinosaur_list
            if (rtrim($pathinfo, '/') === '') {
                if (substr($pathinfo, -1) !== '/') {
                    return $this->redirect($pathinfo.'/', 'dinosaur_list');
                }

                return array (  '_controller' => 'AppBundle\\Controller\\DinosaurController::indexAction',  '_route' => 'dinosaur_list',);
            }

            // dinosaur_show
            if (0 === strpos($pathinfo, '/dinosaurs') && preg_match('#^/dinosaurs/(?P<id>[^/]++)$#s', $pathinfo, $matches)) {
                return $this->mergeDefaults(array_replace($matches, array('_route' => 'dinosaur_show')), array (  '_controller' => 'AppBundle\\Controller\\DinosaurController::showAction',));
            }

            throw 0 < count($allow) ? new MethodNotAllowedException(array_unique($allow)) : new ResourceNotFoundException();
        }
    }

This is the end result of parsing through all of your routes, whether they're
written in annotations, YAML or some other format. So when we see ``$this->matcher->match()``
in ``RouterListener``, that's actually calling the ``match()`` function you
see inside of that cached class. Symfony is smart enough to parse all of
our routes, then generate this big crazy, regex, if-statement matching algorithm.
If we scroll to the bottom, you'll see our dinosaur pages. Ok, this isn't
important to understand, I just think it's cool.

Introducing: The Request attributes
-----------------------------------

So let's get rid of the ``dump()`` in ``RouterListener`` and trace through
what happens next. If you look below, you'll see that it takes that ``$parameters``
array, which has ``_controller`` and ``id``, and it puts onto a ``$request->attributes``
property::

    // vendor/symfony/symfony/src/Symfony/Component/HttpKernel/EventListener/RouterListener.php
    // ...

    public function onKernelRequest(GetResponseEvent $event)
    {
        // ...

        if ($this->matcher instanceof RequestMatcherInterface) {
            $parameters = $this->matcher->matchRequest($request);
        } else {
            $parameters = $this->matcher->match($request->getPathInfo());
        }
        
        // ...

        $request->attributes->add($parameters);

        // ...
    }

Symfony's request object has a bunch of these public properties. I'll open
up the Components documentation for the `HttpFoundation component`_, because
it talks about this.

Every public property except for one, has a real-world equivalent. For example,
if you want to get the query parameters, you say ``$request->query->get()``.
If you want to get the cookies, it's ``$request->cookies->get()``. For the
headers, it's ``$request->headers->get``. All of these are ways to get information
that comes from the original HTTP request message.

The one weird guy is ``$request-attributes``. It has no real-world equivalent.
It's just a place for you to store application-specific information about
the request. And route info is exactly that.

Putting information onto the ``$request->attributes`` property doesn't actually
do anything. It's just a place to store data - it's not triggering any other
systems. We're just modifying the request object, and that's it for the
``RouterListener``.

Let's close this up and go back to ``HttpKernel``. At this point, the *only*
thing we've done is dispatch the ``kernel.request`` event and the only listener
that's really important is the ``RouterListener``. And all it did was modify
the ``$request->attributes``::

    // vendor/symfony/symfony/src/Symfony/Component/HttpKernel/HttpKernel.php
    // ...

    private function handleRaw(Request $request, $type = self::MASTER_REQUEST)
    {
        // ...
        
        // request
        $event = new GetResponseEvent($this, $request, $type);
        $this->dispatcher->dispatch(KernelEvents::REQUEST, $event);

        // ...
    }

So not a lot has happened yet.

Creating the Response Immediately in a Listener
-----------------------------------------------

If we follow this down, there's a really interesting ``if`` statement::

    // vendor/symfony/symfony/src/Symfony/Component/HttpKernel/HttpKernel.php
    // ...

    private function handleRaw(Request $request, $type = self::MASTER_REQUEST)
    {
        // ...

        $this->dispatcher->dispatch(KernelEvents::REQUEST, $event);

        if ($event->hasResponse()) {
            return $this->filterResponse($event->getResponse(), $request, $type);
        }

        // ...
    }

If the event has a response - and I'll show you what that means - it exits
immediately before calling the controller or doing anything else. We'll
look at that ``filterResponse`` method later, but it doesn't do anything
mission critical. 

This means that any listener to ``kernel.request`` can just create a ``Response``
object and say "I'm done". For example, if you had a maintenance mode, you
could create a listener, check some flag to see if you're in maintenance
mode, and return a response immediately that says: "We're fixing some things."

Let's try this. In ``UserAgentSubscriber``, I'll create a new ``Response``
object. Make sure you use the one from the ``HttpFoundation`` component.
PHPStorm did just add a ``use`` statement for me up on line 7. And we'll
say "Come back later"::

    // src/AppBundle/EventListener/UserAgentSubscriber.php
    // ...
    
    use Symfony\Component\HttpFoundation\Response;
    // ...

    public function onKernelRequest(GetResponseEvent $event)
    {
        // ...

        $response = new Response('Come back later');
    }

And the ``GetResponseEvent`` object we're passed has an ``$event->setResponse()``
method on it. Remember, every event passes a different object, and this one
happens to have this ``setResponse`` method on it. We'll make things more
interesting and put this in a block so it only randomly sets the response::

    // src/AppBundle/EventListener/UserAgentSubscriber.php
    // ...
    
    use Symfony\Component\HttpFoundation\Response;
    // ...

    public function onKernelRequest(GetResponseEvent $event)
    {
        // ...

        if (rand(0, 100) > 50) {
            $response = new Response('Come back later');
            $event->setResponse($response);
        }
    }

If we go and refresh now, the page works fine, refresh again... mine is
being stubborn. There we go. You can see that as soon the response is set,
it just stops entirely.

I'll leave this code in there, but let's comment it out so it doesn't ruin
our project::

    if (rand(0, 100) > 50) {
        $response = new Response('Come back later');
        // $event->setResponse($response);
    }

Perfect!

In reality, there's no listener that's setting the response for us. So our
next job will be to figure out which controller function to call to create
the Response.

.. _`HttpFoundation component`: http://symfony.com/doc/current/components/http_foundation/introduction.html
