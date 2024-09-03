<?php
namespace App\Field;

use App\Form\AttachmentType;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FieldTrait;
use Vich\UploaderBundle\Form\Type\VichFileType;

final class VichField implements FieldInterface
{
    use FieldTrait;

    /**
     * @param string|false|null $label
     */
    public static function new(string $propertyName, $label = null): self
    {
        return (new self())
            ->setProperty($propertyName)
            ->setLabel($label)
            ->setTemplatePath('field/vich_field.html.twig')
            ->setFormType(VichFileType::class)
            ->setFormTypeOption('download_uri', false)
            ->setFormTypeOption('allow_delete', false)
            ->setFormTypeOption('delete_label', false)
            ->addCssClass('field-image');
    }
}
