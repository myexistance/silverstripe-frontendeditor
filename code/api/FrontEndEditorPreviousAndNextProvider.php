<?php

/**
 *
 * this class manages the previous and next step
 * it provides functions that are independent from the
 * sequencer being used ....
 */

class FrontEndEditorPreviousAndNextProvider extends Object
{

    /**
     * cached variable for a singleton pattern
     * @var FrontEndEditorPreviousAndNextProvider
     */
    private static $_me_cached = null;

    /**
     * returns a singleton
     * @param string|null $sequencerClassName
     * @param FrontEndEditable|null $currentRecordBeingEdited
     *
     * @return FrontEndEditorPreviousAndNextProvider
     */
    public static function inst($sequencerClassName = null, $currentRecordBeingEdited = null) : FrontEndEditorPreviousAndNextProvider
    {
        if(self::$_me_cached === null) {
            self::$_me_cached = Injector::inst()->get('FrontEndEditorPreviousAndNextProvider');
        }
        if($sequencerClassName) {
            self::$_me_cached->setSequenceProvider($sequencerClassName);
        }
        if($currentRecordBeingEdited) {
            self::$_me_cached->setCurrentRecordBeingEdited($currentRecordBeingEdited);
        }

        return self::$_me_cached;
    }

    /**
     * returns a list of sequences available to the current member
     *
     * @param Member $member
     *
     * @return ArrayList
     */
    public function ListOfSequences($member = null) : ArrayList
    {
        $array = [];
        $list = ClassInfo::subclassesFor('FrontEndEditorPreviousAndNextSequencer');
        unset($list['FrontEndEditorPreviousAndNextSequencer']);
        $currentSequencerClassName = $this->getClassName();
        foreach($list as $className) {
            $class = Injector::inst()->get($className);
            if($class->canView($member)) {
                $explanation = FrontEndEditorSequencerExplanation::add_or_find_item($className);
                $class->Description = $explanation->ShortDescription;
                if($class->class === $currentSequencerClassName) {
                    $class->LinkingMode = 'current';
                } else {
                    $class->LinkingMode = 'link';
                }
                $array[] = $class;
            }
        }
        $arrayList = ArrayList::create($array);

        $this->extend('UpdateListOfSequences', $arrayList);

        return $arrayList;
    }

    public function ArrayOfClassesToSequence()
    {
        return $this->runOnSequencer('ArrayOfClassesToSequence', []);
    }

    /**
     *
     * @var string
     */
    protected $sequencerClassName = '';

    /**
     * @param string $className
     *
     * @return FrontEndEditorPreviousAndNextProvider
     */
    public function setSequenceProvider($className) : FrontEndEditorPreviousAndNextProvider
    {
        $list = ClassInfo::subclassesFor('FrontEndEditorPreviousAndNextSequencer');
        $list = array_change_key_case($list);
        if(isset($list[$className]) && $className !== 'FrontEndEditorPreviousAndNextSequencer') {
            $className = $list[$className];
            $this->sequencerClassName = $className;
            FrontEndEditorSessionManager::set_sequencer($className);
        } else {
            user_error($className.' does not extend FrontEndEditorPreviousAndNextSequencer.');
        }

        return $this;
    }

    /**
     *
     * @param  FrontEndEditable (DataObject)
     *
     * @return FrontEndEditorPreviousAndNextProvider
     */
    public function setCurrentRecordBeingEdited($currentRecordBeingEdited) : FrontEndEditorPreviousAndNextProvider
    {
        $this->runOnSequencer('setCurrentRecordBeingEdited', null, $currentRecordBeingEdited);

        return $this;
    }

    /**
     *
     * @return FrontEndEditable|null
     */
    public function getCurrentRecordBeingEdited()
    {
        return $this->runOnSequencer('getCurrentRecordBeingEdited', null);
    }

    /**
     * a sequencer has been set ...
     * @return bool
     */
    public function HasSequencer() : bool
    {
        return $this->getSequencer() ? true : false;
    }

    /**
     *
     * @return bool
     */
    public function HasCurrentRecordBeingEdited(): bool
    {
        return $this->HasSequencer() && $this->getCurrentRecordBeingEdited() ? true : false;
    }

    private static $_my_sequencer = null;

    /**
     *
     * @return FrontEndEditorPreviousAndNextSequencer
     */
    public function getSequencer()
    {
        if(self::$_my_sequencer === null) {
            $className = $this->getClassName();
            if($className) {
                self::$_my_sequencer = Injector::inst()->get($className);
            }
        }

        return self::$_my_sequencer;
    }

    /**
     *
     * @return string
     */
    protected function getClassName(): string
    {

        if(! $this->sequencerClassName) {
            $this->sequencerClassName = FrontEndEditorSessionManager::get_sequencer();
        }

        return strval($this->sequencerClassName);
    }

    /**
     * to kick start a new sequence
     * this method must set the first record being edited.
     *
     * @return FrontEndEditorPreviousAndNextProvider [description]
     */
    public function StartSequence() : FrontEndEditorPreviousAndNextProvider
    {
        $this->runOnSequencer('StartSequence', null);

        return $this;
    }

    /**
     * force to go to a new page
     * you either pass the new record or the relative position of the new page (e.g. -1 / 1, 2)
     *
     * @param  FrontEndEditable|int $newRecordBeingEditedOrRelativePageNumber
     *
     * @return FrontEndEditorPreviousAndNextProvider
     */
    public function setPage($newRecordBeingEditedOrRelativePageNumber) : FrontEndEditorPreviousAndNextProvider
    {
        $item = null;
        if(is_int($newRecordBeingEditedOrRelativePageNumber)) {
            //find all links
            $links = $this->AllPages();
            $linksAsArray = $links->toArray();
            //find new page number
            $currentPageNumber = $this->getPageNumber();
            $newPageNumber = $currentPageNumber + $newRecordBeingEditedOrRelativePageNumber;
            if(isset($linksAsArray[$newPageNumber])) {
                $item = $linksAsArray[$newPageNumber];
            } else {
                //run again to show error
                user_error('Page set is not valid: '.$newRecordBeingEditedOrRelativePageNumber);

                return $this;
            }
        } elseif($newRecordBeingEditedOrRelativePageNumber instanceof FrontEndEditable) {
            $item = $newRecordBeingEditedOrRelativePageNumber;
        } else {
            user_error('Page set is not valid: '.print_r($newRecordBeingEditedOrRelativePageNumber, 1));

            return $this;
        }
        if($item !== null) {
            $this->setCurrentRecordBeingEdited($item);
        } else {
            user_error('Could not find item');
        }

        return $this;
    }


    /**
     *
     * @return string
     */
    public function Link() : string
    {
        return $this->getPageLink(0);
    }


    /**
     * @param int $offSetFromCurrent
     *
     * @return string
     */
    public function getPageLink($offSetFromCurrent = 0) : string
    {
        $item = null;
        if($offSetFromCurrent !== 0) {
            $item = $this->getPageItem($offSetFromCurrent);
        }
        return $this->runOnSequencer('getPageLink', '404-page-not-found-for-sequencer', $item);
    }

    /**
     *
     * @return int
     */
    public function TotalNumberOfPages() : int
    {
        return $this->runOnSequencer('TotalNumberOfPages', 0);
    }

    /**
     * @param string $className OPTIONAL
     * @return ArrayList
     */
    public function AllPages($className = null) : ArrayList
    {
        return $this->runOnSequencer('AllPages', ArrayList::create(), $className);
    }

    /**
     *
     * @param string $className (OPTIONAL)
     *
     * @return FrontEndEditable|null
     */
    public function AddAnotherOfThisClass($className = null)
    {
        return $this->runOnSequencer('AddAnotherOfThisClass', null, $className);
    }

    /**
     * is there another page to work through?
     *
     * @param  string|null $classNMame
     * @return bool
     */
    public function HasNextPage($className = null) : bool
    {
        return $this->runOnSequencer('HasNextPage', false, $className);
    }

    /**
     *
     * @return string
     */
    public function NextPageLink() : string
    {
        return $this->getPageLink(1);
    }

    /**
     * @return FrontEndEditable|null
     */
    public function NextPageObject()
    {
        return $this->runOnSequencer('NextPageObject', null);
    }

    /**
     *
     * @return string
     */
    public function goNextPage() : string
    {
        $this->setPage(1);

        return $this->getPageLink(0);
    }

    /**
     * is there a previous page to work through?
     *
     * @param  string|null $classNMame
     * @return bool
     */
    public function HasPreviousPage($className = null) : bool
    {
        return $this->runOnSequencer('HasPreviousPage', false, $className);
    }

    /**
     *
     * @return string
     */
    public function PreviousLink() : string
    {
        return $this->getPageLink(-1);
    }

    /**
     * @return FrontEndEditable|null
     */
    public function PreviousPageObject()
    {
        return $this->runOnSequencer('PreviousPageObject', null);
    }

    /**
     *
     * @return string
     */
    public function goPreviousPage() : string
    {
        $this->setPage(-1);

        return $this->getPageLink(0);
    }



    /**
     * @param string $className
     *
     * @return FrontEndEditable|null
     */
    protected function CanAddAnotherOfThisClass($className) : bool
    {
        return $this->runOnSequencer('CanAddAnotherOfThisClass', false, $className);
    }


    /**
     * @param int|string|null $pageNumberOrFrontEndUID
     *
     * @return FrontEndEditable|null
     */
    protected function getPageItem($pageNumberOrFrontEndUID) : FrontEndEditable
    {
        return $this->runOnSequencer('getPageItem', null, $pageNumberOrFrontEndUID);
    }

    /**
     *
     * @return int
     */
    protected function getPageNumber() : int
    {
        return $this->runOnSequencer('getPageNumber', 0);
    }

    protected function FrontEndUID() : string
    {
        return $this->runOnSequencer('FrontEndUID', 'DataObject,0');
    }

    /**
     * run a method in the sequencer ..
     *
     * @param  string $method      the method to run
     * @param  mixed $backupValue  what to return when there is no sequencer
     * @param  mixed $param1       first parameter
     * @param  mixed $param2       second parameter
     * @param  mixed $param3       third parameter
     *
     * @return mixed
     */
    public function runOnSequencer($method, $backupValue, $param1 = null, $param2 = null, $param3 = null)
    {
        $sequencer = $this->getSequencer();
        if($sequencer) {
            if($param1 !== null) {
                if($param2 !== null) {
                    if($param3 !== null) {
                        return $sequencer->$method($param1, $param2, $param3);
                    }
                    return $sequencer->$method($param1, $param2);
                }
                return $sequencer->$method($param1);
            }
            return $sequencer->$method();
        }

        return $backupValue;
    }


}
