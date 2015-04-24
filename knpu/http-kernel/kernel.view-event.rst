The kernel.view Event
=====================

We've journeyed far, brave traveler. And now that Symfony has called our
controller, let's start our adventure home.

*Our* controller is returning a Response object. We're calling ``render()``
and that's a shortcut that renders the template and put it inside of a Response
object::

    // src/AppBundle/Controller/DinosaurController.php
    // ...

    public function showAction($id)
    {
        // ...

        return $this->render('dinosaurs/show.html.twig', [
            'dino' => $dino,
        ]);
    }

And even though I love to say that the one job of your controller is to create
and return a ``Response``, it's a lie! You can return whatever you want from
a controller.

And if you don't return a response, what does Symfony do? It dispatches another
event::

    // vendor/symfony/symfony/src/Symfony/Component/HttpKernel/HttpKernel.php
    // ..

    private function handleRaw(Request $request, $type = self::MASTER_REQUEST)
    {
        // ... 

        // call controller
        $response = call_user_func_array($controller, $arguments);

        if (!$response instanceof Response) {
            $event = new GetResponseForControllerResultEvent($this, $request, $type, $response);
            $this->dispatcher->dispatch(KernelEvents::VIEW, $event);

            if ($event->hasResponse()) {
                $response = $event->getResponse();
            }

            // ...
        }

        // ...
    }

This time it's called ``kernel.view``. The purpose of a listener to this
event is to save Symfony. It really *needs* a Response object, and if the
controller didn't give it to us, it calls each listener to this event and
says: "Hey, here's what the controller *did* return, can you somehow transform
this into a real response and save the day?"

Ok, but what's the use-case for this? In a true MVC framework, the controller
is supposed to just return some data, not a response. For example, imagine
if our ``DinosaurController::showAction`` just returned a Dinosaur object
instead of the HTML response. And *then*, imagine we added a listener on
this event that saw that ``Dinosaur`` object, rendered the template and created
the Response for us.

The result would be the same, but we'd be splitting things into two pieces.
The fetching and preparation of the data would happen in the controller. The
creation of a representation of that data would happen in the listener.

To some of you, this might sound ridiculous. I mean, why do all this extra
work? One *real* use-case is for APIs. Imagine the endpoints of your API
need to be able to return both HTML or JSON depending on the ``Accept`` header
sent by the client. In your controller, instead of having a big ``if`` statement
for the two different formats, it just return the data::

    // src/AppBundle/Controller/DinosaurController.php
    // ...

    public function showAction($id)
    {
        $dino = $this->getDoctrine()
            ->getRepository('AppBundle:Dinosaur')
            ->find($id);

        // ...

        return $dino;
    }

Then you'd have a listener on ``kernel.view`` that first checks to see if
the user wants HTML or JSON. If the user wants HTML, it would render the
template. If it wants JSON, it would take that Dinosaur object and turn it
into JSON::

    // some imaginary listener class
    
    public function onKernelView(GetResponseForControllerResultEvent $event)
    {
        $format = $this->checkAcceptHeader($event->getRequest());
        $dino = $event->getControllerResult();
        if ($format == 'html') {
            $html = $this->templating->render('dinosaurs/show.html.twig', [
                'dino' => $dino,
            ]);
        
            return new Response($html);
        } elseif ($format == 'json') {
            $json = $this->serializeToJson($dino);
        
            return new Response($json, 200, [
                'Content-Type' => 'application/json'
            ]);
        }
    }

The `FOSRestBundle`_ has something that does exactly this: you can return
data from your controller, and it has a listener on the ``kernel.view`` event
that transforms that data into whatever response is asked for.

.. tip::

    If you register controllers as services, there's an additional use-case
    for this. See `Lightweight Symfony2 Controllers`_.

In normal Symfony, there's nothing important that listens to this event. But
when it's dispatched, it's hoping that one of those listeners will be able
to set the response on the ``$event`` object. If we *still* don't have a
``Response``, *that's* when you get the error that the controller must return
a ``Response``. Oh, and this is one of my favorite parts of the code: if
the ``$response`` is actually null, and it says "Hey, *maybe* you forgot
to add a return statement somewhere in your controller?"::

    // vendor/symfony/symfony/src/Symfony/Component/HttpKernel/HttpKernel.php
    // ..

    private function handleRaw(Request $request, $type = self::MASTER_REQUEST)
    {
        // ...

        if (!$response instanceof Response) {
            // dispatch the event
            // ...

            if ($event->hasResponse()) {
                $response = $event->getResponse();
            }

            if (!$response instanceof Response) {
                $msg = sprintf('The controller must return a response (%s given).', $this->varToString($response));

                // the user may have forgotten to return something
                if (null === $response) {
                    $msg .= ' Did you forget to add a return statement somewhere in your controller?';
                }
                throw new \LogicException($msg);
            }
        }

        // ...
    }

Yep, I've seen that error a few times in my days.

Since *our* controller *is* returning a Response, if we go back and look
at the events tab in the profiler, we'll see that there's no ``kernel.view``
in the list at the top. But below there's a "Not Called Listeners" section,
and there *is* one listener to ``kernel.view``, which comes from the `SensioFrameworkExtraBundle`_,
that's not being executed.

.. _`Lightweight Symfony2 Controllers`: http://www.whitewashing.de/2014/10/14/lightweight_symfony2_controllers.html
.. _`FOSRestBundle`: https://github.com/FriendsOfSymfony/FOSRestBundle/blob/master/Resources/doc/3-listener-support.rst#view-response-listener
.. _`SensioFrameworkExtraBundle`: http://symfony.com/doc/current/bundles/SensioFrameworkExtraBundle/annotations/view.html
