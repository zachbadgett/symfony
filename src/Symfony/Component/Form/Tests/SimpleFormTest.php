<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests;

use Symfony\Component\Form\Form;
use Symfony\Component\Form\Util\PropertyPath;
use Symfony\Component\Form\FormConfig;
use Symfony\Component\Form\FormView;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\Exception\TransformationFailedException;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Form\Tests\Fixtures\FixedDataTransformer;
use Symfony\Component\Form\Tests\Fixtures\FixedFilterListener;

class SimpleFormTest extends AbstractFormTest
{
    public function testDataIsInitializedEmpty()
    {
        $model = new FixedDataTransformer(array(
            '' => 'foo',
        ));
        $view = new FixedDataTransformer(array(
            'foo' => 'bar',
        ));

        $config = new FormConfig('name', null, $this->dispatcher);
        $config->addViewTransformer($view);
        $config->addModelTransformer($model);
        $form = new Form($config);

        $this->assertNull($form->getData());
        $this->assertSame('foo', $form->getNormData());
        $this->assertSame('bar', $form->getViewData());
    }

    /**
     * @expectedException Symfony\Component\Form\Exception\AlreadyBoundException
     */
    public function testBindThrowsExceptionIfAlreadyBound()
    {
        $this->form->bind(array());
        $this->form->bind(array());
    }

    public function testBindIsIgnoredIfDisabled()
    {
        $form = $this->getBuilder()
            ->setDisabled(true)
            ->setData('initial')
            ->getForm();

        $form->bind('new');

        $this->assertEquals('initial', $form->getData());
        $this->assertTrue($form->isBound());
    }

    public function testNeverRequiredIfParentNotRequired()
    {
        $parent = $this->getBuilder()->setRequired(false)->getForm();
        $child = $this->getBuilder()->setRequired(true)->getForm();

        $child->setParent($parent);

        $this->assertFalse($child->isRequired());
    }

    public function testRequired()
    {
        $parent = $this->getBuilder()->setRequired(true)->getForm();
        $child = $this->getBuilder()->setRequired(true)->getForm();

        $child->setParent($parent);

        $this->assertTrue($child->isRequired());
    }

    public function testNotRequired()
    {
        $parent = $this->getBuilder()->setRequired(true)->getForm();
        $child = $this->getBuilder()->setRequired(false)->getForm();

        $child->setParent($parent);

        $this->assertFalse($child->isRequired());
    }

    public function testAlwaysDisabledIfParentDisabled()
    {
        $parent = $this->getBuilder()->setDisabled(true)->getForm();
        $child = $this->getBuilder()->setDisabled(false)->getForm();

        $child->setParent($parent);

        $this->assertTrue($child->isDisabled());
    }

    public function testDisabled()
    {
        $parent = $this->getBuilder()->setDisabled(false)->getForm();
        $child = $this->getBuilder()->setDisabled(true)->getForm();

        $child->setParent($parent);

        $this->assertTrue($child->isDisabled());
    }

    public function testNotDisabled()
    {
        $parent = $this->getBuilder()->setDisabled(false)->getForm();
        $child = $this->getBuilder()->setDisabled(false)->getForm();

        $child->setParent($parent);

        $this->assertFalse($child->isDisabled());
    }

    public function testGetRootReturnsRootOfParent()
    {
        $parent = $this->getMockForm();
        $parent->expects($this->once())
            ->method('getRoot')
            ->will($this->returnValue('ROOT'));

        $this->form->setParent($parent);

        $this->assertEquals('ROOT', $this->form->getRoot());
    }

    public function testGetRootReturnsSelfIfNoParent()
    {
        $this->assertSame($this->form, $this->form->getRoot());
    }

    public function testEmptyIfEmptyArray()
    {
        $this->form->setData(array());

        $this->assertTrue($this->form->isEmpty());
    }

    public function testEmptyIfNull()
    {
        $this->form->setData(null);

        $this->assertTrue($this->form->isEmpty());
    }

    public function testEmptyIfEmptyString()
    {
        $this->form->setData('');

        $this->assertTrue($this->form->isEmpty());
    }

    public function testNotEmptyIfText()
    {
        $this->form->setData('foobar');

        $this->assertFalse($this->form->isEmpty());
    }

    public function testValidIfBound()
    {
        $form = $this->getBuilder()->getForm();
        $form->bind('foobar');

        $this->assertTrue($form->isValid());
    }

    public function testValidIfBoundAndDisabled()
    {
        $form = $this->getBuilder()->setDisabled(true)->getForm();
        $form->bind('foobar');

        $this->assertTrue($form->isValid());
    }

    /**
     * @expectedException \LogicException
     */
    public function testNotValidIfNotBound()
    {
        $this->form->isValid();
    }

    public function testNotValidIfErrors()
    {
        $form = $this->getBuilder()->getForm();
        $form->bind('foobar');
        $form->addError(new FormError('Error!'));

        $this->assertFalse($form->isValid());
    }

    public function testHasErrors()
    {
        $this->form->addError(new FormError('Error!'));

        $this->assertTrue($this->form->hasErrors());
    }

    public function testHasNoErrors()
    {
        $this->assertFalse($this->form->hasErrors());
    }

    /**
     * @expectedException Symfony\Component\Form\Exception\AlreadyBoundException
     */
    public function testSetParentThrowsExceptionIfAlreadyBound()
    {
        $this->form->bind(array());
        $this->form->setParent($this->getBuilder('parent')->getForm());
    }

    public function testBound()
    {
        $form = $this->getBuilder()->getForm();
        $form->bind('foobar');

        $this->assertTrue($form->isBound());
    }

    public function testNotBound()
    {
        $this->assertFalse($this->form->isBound());
    }

    /**
     * @expectedException Symfony\Component\Form\Exception\AlreadyBoundException
     */
    public function testSetDataThrowsExceptionIfAlreadyBound()
    {
        $this->form->bind(array());
        $this->form->setData(null);
    }

    public function testSetDataClonesObjectIfNotByReference()
    {
        $data = new \stdClass();
        $form = $this->getBuilder('name', null, '\stdClass')->setByReference(false)->getForm();
        $form->setData($data);

        $this->assertNotSame($data, $form->getData());
        $this->assertEquals($data, $form->getData());
    }

    public function testSetDataDoesNotCloneObjectIfByReference()
    {
        $data = new \stdClass();
        $form = $this->getBuilder('name', null, '\stdClass')->setByReference(true)->getForm();
        $form->setData($data);

        $this->assertSame($data, $form->getData());
    }

    public function testSetDataExecutesTransformationChain()
    {
        // use real event dispatcher now
        $form = $this->getBuilder('name', new EventDispatcher())
            ->addEventSubscriber(new FixedFilterListener(array(
            'preSetData' => array(
                'app' => 'filtered',
            ),
        )))
            ->addModelTransformer(new FixedDataTransformer(array(
            '' => '',
            'filtered' => 'norm',
        )))
            ->addViewTransformer(new FixedDataTransformer(array(
            '' => '',
            'norm' => 'client',
        )))
            ->getForm();

        $form->setData('app');

        $this->assertEquals('filtered', $form->getData());
        $this->assertEquals('norm', $form->getNormData());
        $this->assertEquals('client', $form->getViewData());
    }

    public function testSetDataExecutesViewTransformersInOrder()
    {
        $form = $this->getBuilder()
            ->addViewTransformer(new FixedDataTransformer(array(
            '' => '',
            'first' => 'second',
        )))
            ->addViewTransformer(new FixedDataTransformer(array(
            '' => '',
            'second' => 'third',
        )))
            ->getForm();

        $form->setData('first');

        $this->assertEquals('third', $form->getViewData());
    }

    public function testSetDataExecutesModelTransformersInReverseOrder()
    {
        $form = $this->getBuilder()
            ->addModelTransformer(new FixedDataTransformer(array(
            '' => '',
            'second' => 'third',
        )))
            ->addModelTransformer(new FixedDataTransformer(array(
            '' => '',
            'first' => 'second',
        )))
            ->getForm();

        $form->setData('first');

        $this->assertEquals('third', $form->getNormData());
    }

    /*
     * When there is no data transformer, the data must have the same format
     * in all three representations
     */
    public function testSetDataConvertsScalarToStringIfNoTransformer()
    {
        $form = $this->getBuilder()->getForm();

        $form->setData(1);

        $this->assertSame('1', $form->getData());
        $this->assertSame('1', $form->getNormData());
        $this->assertSame('1', $form->getViewData());
    }

    /*
     * Data in client format should, if possible, always be a string to
     * facilitate differentiation between '0' and ''
     */
    public function testSetDataConvertsScalarToStringIfOnlyModelTransformer()
    {
        $form = $this->getBuilder()
            ->addModelTransformer(new FixedDataTransformer(array(
            '' => '',
            1 => 23,
        )))
            ->getForm();

        $form->setData(1);

        $this->assertSame(1, $form->getData());
        $this->assertSame(23, $form->getNormData());
        $this->assertSame('23', $form->getViewData());
    }

    /*
     * NULL remains NULL in app and norm format to remove the need to treat
     * empty values and NULL explicitely in the application
     */
    public function testSetDataConvertsNullToStringIfNoTransformer()
    {
        $form = $this->getBuilder()->getForm();

        $form->setData(null);

        $this->assertNull($form->getData());
        $this->assertNull($form->getNormData());
        $this->assertSame('', $form->getViewData());
    }

    public function testBindConvertsEmptyToNullIfNoTransformer()
    {
        $form = $this->getBuilder()->getForm();

        $form->bind('');

        $this->assertNull($form->getData());
        $this->assertNull($form->getNormData());
        $this->assertSame('', $form->getViewData());
    }

    public function testBindExecutesTransformationChain()
    {
        // use real event dispatcher now
        $form = $this->getBuilder('name', new EventDispatcher())
            ->addEventSubscriber(new FixedFilterListener(array(
            'preBind' => array(
                'client' => 'filteredclient',
            ),
            'onBind' => array(
                'norm' => 'filterednorm',
            ),
        )))
            ->addViewTransformer(new FixedDataTransformer(array(
            '' => '',
            // direction is reversed!
            'norm' => 'filteredclient',
            'filterednorm' => 'cleanedclient'
        )))
            ->addModelTransformer(new FixedDataTransformer(array(
            '' => '',
            // direction is reversed!
            'app' => 'filterednorm',
        )))
            ->getForm();

        $form->setData('app');

        $this->assertEquals('app', $form->getData());
        $this->assertEquals('filterednorm', $form->getNormData());
        $this->assertEquals('cleanedclient', $form->getViewData());
    }

    public function testBindExecutesViewTransformersInReverseOrder()
    {
        $form = $this->getBuilder()
            ->addViewTransformer(new FixedDataTransformer(array(
            '' => '',
            'third' => 'second',
        )))
            ->addViewTransformer(new FixedDataTransformer(array(
            '' => '',
            'second' => 'first',
        )))
            ->getForm();

        $form->bind('first');

        $this->assertEquals('third', $form->getNormData());
    }

    public function testBindExecutesModelTransformersInOrder()
    {
        $form = $this->getBuilder()
            ->addModelTransformer(new FixedDataTransformer(array(
            '' => '',
            'second' => 'first',
        )))
            ->addModelTransformer(new FixedDataTransformer(array(
            '' => '',
            'third' => 'second',
        )))
            ->getForm();

        $form->bind('first');

        $this->assertEquals('third', $form->getData());
    }

    public function testSynchronizedByDefault()
    {
        $this->assertTrue($this->form->isSynchronized());
    }

    public function testSynchronizedAfterBinding()
    {
        $this->form->bind('foobar');

        $this->assertTrue($this->form->isSynchronized());
    }

    public function testNotSynchronizedIfTransformationFailed()
    {
        $transformer = $this->getDataTransformer();
        $transformer->expects($this->once())
            ->method('reverseTransform')
            ->will($this->throwException(new TransformationFailedException()));

        $form = $this->getBuilder()
            ->addViewTransformer($transformer)
            ->getForm();

        $form->bind('foobar');

        $this->assertFalse($form->isSynchronized());
    }

    public function testEmptyDataCreatedBeforeTransforming()
    {
        $form = $this->getBuilder()
            ->setEmptyData('foo')
            ->addViewTransformer(new FixedDataTransformer(array(
            '' => '',
            // direction is reversed!
            'bar' => 'foo',
        )))
            ->getForm();

        $form->bind('');

        $this->assertEquals('bar', $form->getData());
    }

    public function testEmptyDataFromClosure()
    {
        $test = $this;
        $form = $this->getBuilder()
            ->setEmptyData(function ($form) use ($test) {
            // the form instance is passed to the closure to allow use
            // of form data when creating the empty value
            $test->assertInstanceOf('Symfony\Component\Form\FormInterface', $form);

            return 'foo';
        })
            ->addViewTransformer(new FixedDataTransformer(array(
            '' => '',
            // direction is reversed!
            'bar' => 'foo',
        )))
            ->getForm();

        $form->bind('');

        $this->assertEquals('bar', $form->getData());
    }

    public function testBindValidatesAfterTransformation()
    {
        $test = $this;
        $validator = $this->getFormValidator();
        $form = $this->getBuilder()
            ->addValidator($validator)
            ->getForm();

        $validator->expects($this->once())
            ->method('validate')
            ->with($form)
            ->will($this->returnCallback(function ($form) use ($test) {
            $test->assertEquals('foobar', $form->getData());
        }));

        $form->bind('foobar');
    }

    public function testBindResetsErrors()
    {
        $this->form->addError(new FormError('Error!'));
        $this->form->bind('foobar');

        $this->assertSame(array(), $this->form->getErrors());
    }

    public function testCreateViewAcceptsParent()
    {
        $parent = new FormView('form');

        $view = $this->form->createView($parent);

        $this->assertSame($parent, $view->getParent());
    }

    public function testGetErrorsAsString()
    {
        $this->form->addError(new FormError('Error!'));

        $this->assertEquals("ERROR: Error!\n", $this->form->getErrorsAsString());
    }

    public function testFormCanHaveEmptyName()
    {
        $form = $this->getBuilder('')->getForm();

        $this->assertEquals('', $form->getName());
    }

    /**
     * @expectedException Symfony\Component\Form\Exception\FormException
     * @expectedExceptionMessage A form with an empty name cannot have a parent form.
     */
    public function testFormCannotHaveEmptyNameNotInRootLevel()
    {
        $this->getBuilder()
            ->setCompound(true)
            ->setDataMapper($this->getDataMapper())
            ->add($this->getBuilder(''))
            ->getForm();
    }

    public function testGetPropertyPathReturnsConfiguredPath()
    {
        $form = $this->getBuilder()->setPropertyPath('address.street')->getForm();

        $this->assertEquals(new PropertyPath('address.street'), $form->getPropertyPath());
    }

    // see https://github.com/symfony/symfony/issues/3903
    public function testGetPropertyPathDefaultsToNameIfParentHasDataClass()
    {
        $parent = $this->getBuilder(null, null, 'stdClass')
            ->setCompound(true)
            ->setDataMapper($this->getDataMapper())
            ->getForm();
        $form = $this->getBuilder('name')->getForm();
        $parent->add($form);

        $this->assertEquals(new PropertyPath('name'), $form->getPropertyPath());
    }

    // see https://github.com/symfony/symfony/issues/3903
    public function testGetPropertyPathDefaultsToIndexedNameIfParentDataClassIsNull()
    {
        $parent = $this->getBuilder()
            ->setCompound(true)
            ->setDataMapper($this->getDataMapper())
            ->getForm();
        $form = $this->getBuilder('name')->getForm();
        $parent->add($form);

        $this->assertEquals(new PropertyPath('[name]'), $form->getPropertyPath());
    }

    /**
     * @expectedException Symfony\Component\Form\Exception\FormException
     */
    public function testViewDataMustNotBeObjectIfDataClassIsNull()
    {
        $config = new FormConfig('name', null, $this->dispatcher);
        $config->addViewTransformer(new FixedDataTransformer(array(
            '' => '',
            'foo' => new \stdClass(),
        )));
        $form = new Form($config);

        $form->setData('foo');
    }

    public function testViewDataMayBeArrayAccessIfDataClassIsNull()
    {
        $arrayAccess = $this->getMock('\ArrayAccess');
        $config = new FormConfig('name', null, $this->dispatcher);
        $config->addViewTransformer(new FixedDataTransformer(array(
            '' => '',
            'foo' => $arrayAccess,
        )));
        $form = new Form($config);

        $form->setData('foo');

        $this->assertSame($arrayAccess, $form->getViewData());
    }

    /**
     * @expectedException Symfony\Component\Form\Exception\FormException
     */
    public function testViewDataMustBeObjectIfDataClassIsSet()
    {
        $config = new FormConfig('name', 'stdClass', $this->dispatcher);
        $config->addViewTransformer(new FixedDataTransformer(array(
            '' => '',
            'foo' => array('bar' => 'baz'),
        )));
        $form = new Form($config);

        $form->setData('foo');
    }

    protected function createForm()
    {
        return $this->getBuilder()->getForm();
    }
}
